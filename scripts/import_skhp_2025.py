#!/usr/bin/env python3
"""Impor arsip SKHP 2025 yang telah diperiksa ke basis data Setimbang.

Persiapan: scripts/analyze_skhp_2025.py, scripts/prepare_skhp_2025.py,
           scripts/convert_skhp_2025.py, database/012_import_skhp_2025.sql.
Jalankan dengan Python yang memiliki PyMySQL. --dry-run tidak menulis data.
"""

import argparse
import csv
import datetime as dt
import hashlib
import json
import os
import pwd
import re
import secrets
import shutil
import subprocess
from collections import Counter, defaultdict
from pathlib import Path

import pymysql

ROOT = Path(__file__).resolve().parents[1]
PLAN = Path('/tmp/metro-skhp-plan.json')
PDF_MANIFEST = Path('/tmp/metro-skhp-stage/manifest.json')
DOC_ROOT = Path('/var/lib/metro/documents')
PRIVATE_ROOT = Path('/var/lib/metro/imports')
BACKUP_ROOT = Path('/var/backups/metro')


def db_config():
    php = ('define("BASEPATH",getcwd()."/system/");'
           'define("ENVIRONMENT","production");'
           'require "application/config/database.php";'
           'echo json_encode($db["default"]);')
    return json.loads(subprocess.check_output(['php', '-r', php], cwd=ROOT))


def connect():
    config = db_config()
    return pymysql.connect(host=config['hostname'], user=config['username'],
                           password=config['password'], database=config['database'],
                           charset='utf8mb4', autocommit=False,
                           cursorclass=pymysql.cursors.DictCursor)


def simple(s):
    return re.sub(r'[^A-Z0-9]', '', (s or '').upper())


def norm_name(s):
    return simple(re.sub(r'\s*\(WAREHOUSE\)\s*$', '', s or '', flags=re.I))


def fetch_one(cur, query, params=()):
    cur.execute(query, params)
    return cur.fetchone()


def fetch_all(cur, query, params=()):
    cur.execute(query, params)
    return cur.fetchall()


def insert(cur, table, row):
    columns = list(row)
    sql = ('INSERT INTO ' + table + ' (' + ','.join(columns) + ') VALUES ('
           + ','.join(['%s'] * len(columns)) + ')')
    cur.execute(sql, [row[k] for k in columns])
    return cur.lastrowid


def timestamp(date, time='00:00:00'):
    return date + ' ' + time


def owner_kind(name):
    people = {'AHMAD SAMSUDIN', 'HERI PAMUNGKAS', 'JUWENI', 'LASIRAN',
              'SAWI', 'SITI MUANIS', 'SULASIH'}
    return 'individual' if name.upper().strip() in people else 'organization'


def region(address, districts, villages):
    m = re.search(r'\bKec(?:amatan)?\.?\s*([A-Za-z]+)', address or '', re.I)
    district = districts.get(simple(m.group(1))) if m else None
    village = None
    if district:
        v = re.search(r'\b(?:Ds|Desa)\.?\s*([A-Za-z ]+?)\s*(?:,|\bKec\b)', address or '', re.I)
        if v:
            village = villages.get((district['id'], simple(v.group(1))))
    return district, village


def source_note(doc, certificate_owners):
    notes = []
    if doc['reported_order_no'] != doc['order_no']:
        notes.append('Nomor order SKHP: ' + doc['reported_order_no']
                     + '; buku order: ' + doc['order_no'] + '.')
    if len(certificate_owners[doc['certificate_no']]) > 1:
        notes.append('Nomor SKHP ini juga tertulis pada berkas pemilik lain; nomor asli dipertahankan.')
    if doc['order_date'] > doc['tested_at']:
        notes.append('Tanggal order pada dokumen lebih akhir daripada tanggal pengujian.')
    if doc['type_code'] == 'PUBBM' and isinstance(doc['book_count'], int) and doc['book_count'] != len(doc['units']):
        notes.append('Jumlah unit menurut lampiran SKHP ' + str(len(doc['units']))
                     + ', sedangkan buku order ' + str(doc['book_count']) + '.')
    return ' '.join(notes) or None


def document_name(doc):
    number = doc['certificate_no'].replace('/', '-').replace(' ', '')
    return ('SKHP 2025 ' + number + ' - ' + doc['owner'] + '.pdf')[:200]


def asset_key(profile_id, doc, unit, duplicate=False):
    serial = simple(unit['serial'])
    if not serial:
        return ('source', doc['file'], unit['row'])
    if doc['type_code'] == 'PUBBM' or len(doc['units']) > 1:
        distinguishing = simple(unit['type_name'] + '|' + unit['capacity'])
    else:
        distinguishing = simple(unit['brand'])
    parts = ('serial', profile_id, doc['type_code'], serial,
             distinguishing, simple(doc['address']))
    return (*parts, unit['row']) if duplicate else parts


def password_hashes(passwords):
    if not passwords:
        return []
    php = ('$items=json_decode(stream_get_contents(STDIN),true);'
           '$result=[];foreach($items as $value)$result[]=password_hash($value,PASSWORD_DEFAULT);'
           'echo json_encode($result);')
    result = subprocess.run(['php', '-r', php], input=json.dumps(passwords).encode(),
                            stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=True)
    return json.loads(result.stdout)


def validate(plan, manifest):
    files = [d['file'] for d in plan]
    if len(files) != len(set(files)) or len(files) != 228:
        raise RuntimeError('Daftar SKHP bukan 228 berkas unik.')
    for doc in plan:
        if not all(doc[k] for k in ['certificate_no', 'owner', 'owner_key', 'address',
                                   'type_code', 'order_no', 'order_date', 'tested_at', 'issued_at']):
            raise RuntimeError('Data SKHP belum lengkap: ' + doc['file'])
        if any(not day.startswith('2025-') for day in [doc['order_date'], doc['tested_at'], doc['issued_at']]):
            raise RuntimeError('Tahun tanggal di luar arsip: ' + doc['file'])
        if not doc['units']:
            raise RuntimeError('UTTP kosong: ' + doc['file'])
        pdf = Path(manifest.get(doc['file'], ''))
        if not pdf.is_file() or pdf.stat().st_size < 100 or pdf.stat().st_size > 5 * 1024 * 1024:
            raise RuntimeError('PDF hilang atau tidak sesuai ukuran: ' + doc['file'])
        with pdf.open('rb') as handle:
            if handle.read(5) != b'%PDF-':
                raise RuntimeError('Berkas bukan PDF: ' + doc['file'])


def run(dry_run, simulate=False):
    plan = json.loads(PLAN.read_text())
    manifest = json.loads(PDF_MANIFEST.read_text())
    validate(plan, manifest)
    owners = sorted({d['owner_key'] for d in plan})
    groups = defaultdict(list)
    certificate_owners = defaultdict(set)
    for d in plan:
        groups[d['request_group']].append(d)
        certificate_owners[d['certificate_no']].add(d['owner_key'])
    grouped = sorted(groups.items())
    counts = Counter()
    print('Rencana:', len(owners), 'pemilik,', len(groups), 'riwayat order,',
          sum(len(d['units']) for d in plan), 'baris UTTP,', len(plan), 'PDF SKHP.')
    print('Nomor SKHP ganda dari sumber:',
          [(n, sorted(v)) for n, v in certificate_owners.items() if len(v) > 1])

    connection = connect()
    copies = []
    credentials = []
    private_csv = None
    committed = False
    try:
        with connection.cursor() as cur:
            present = fetch_one(cur, "SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='legacy_skhp_files'")['n']
            if not present and not dry_run:
                raise RuntimeError('Jalankan database/012_import_skhp_2025.sql dahulu.')
            existing_files = {r['source_file'] for r in fetch_all(cur, 'SELECT source_file FROM legacy_skhp_files')} if present else set()
            pending = [d for d in plan if d['file'] not in existing_files]
            print('Belum diimpor:', len(pending), 'berkas; sudah ada:', len(existing_files), 'berkas.')
            if dry_run:
                connection.rollback()
                return
            if not pending:
                connection.rollback()
                return
            if not BACKUP_ROOT.exists() or not list(BACKUP_ROOT.glob('metro_before_skhp2025_*.sql.gz')):
                raise RuntimeError('Cadangan basis data sebelum impor tidak ditemukan.')

            types = {r['type_code']: r for r in fetch_all(cur, 'SELECT id,type_code FROM uttp_types')}
            if 'TIMBANGAN_ANALITIK' not in types:
                type_id = insert(cur, 'uttp_types', {'type_code': 'TIMBANGAN_ANALITIK',
                    'type_name': 'Timbangan analitik', 'requires_capacity': 1,
                    'certificate_scope': 'unit', 'sort_order': 22, 'is_active': 1})
                types['TIMBANGAN_ANALITIK'] = {'id': type_id}
            for d in plan:
                if d['type_code'] not in types:
                    raise RuntimeError('Jenis UTTP belum ada: ' + d['type_code'])

            penera = {r['option_code']: r['id'] for r in fetch_all(cur,
                        "SELECT id,option_code FROM master_options WHERE group_code='penera'")}
            for code, label in [('SUSILO_ADI','Susilo Adi Prayitno, S.H'),
                                ('MULAT_PARIPIH','Mulat Paripih Irianto, S.Si')]:
                if code not in penera:
                    penera[code] = insert(cur, 'master_options', {'group_code': 'penera',
                        'option_code': code, 'option_label': label,
                        'description': 'Penera pada arsip SKHP 2025', 'sort_order': 900,
                        'is_active': 0})
            if 'APRILIA_FONI' not in penera:
                raise RuntimeError('Master penera Aprilia tidak ditemukan.')

            districts = {simple(r['district_name']): r for r in fetch_all(cur,
                         'SELECT id,district_name FROM master_districts')}
            villages = {(r['district_id'], simple(r['village_name'])): r for r in fetch_all(cur,
                        'SELECT id,district_id,village_name FROM master_villages')}
            applicant_role = fetch_one(cur, "SELECT id FROM roles WHERE role_code='applicant'")
            if not applicant_role:
                raise RuntimeError('Peran pemohon tidak ditemukan.')
            account_map = {r['owner_key']: r['user_id'] for r in fetch_all(cur,
                          'SELECT owner_key,user_id FROM legacy_skhp_accounts')}
            profiles = {r['user_id']: r for r in fetch_all(cur,
                        'SELECT id,user_id,organization_name,address FROM applicant_profiles')}
            existing_names = defaultdict(list)
            for r in fetch_all(cur, "SELECT u.id,u.full_name,u.email,u.account_type,p.organization_name FROM users u JOIN applicant_profiles p ON p.user_id=u.id"):
                if r['account_type'] == 'applicant':
                    existing_names[norm_name(r['organization_name'] or r['full_name'])].append(r['id'])
            email_set = {r['email'].lower() for r in fetch_all(cur,
                         'SELECT email FROM users WHERE email IS NOT NULL')}

            new_owner_keys = [key for key in owners if key not in account_map
                              and not existing_names.get(key)]
            initial_password = os.environ.get('SKHP_INITIAL_PASSWORD', '')
            if new_owner_keys and not (8 <= len(initial_password) <= 72):
                raise RuntimeError('Isi SKHP_INITIAL_PASSWORD (8–72 karakter) untuk akun baru.')
            passwords = [initial_password] * len(new_owner_keys)
            hashed = dict(zip(new_owner_keys, password_hashes(passwords)))
            plain = dict(zip(new_owner_keys, passwords))
            representative = {}
            for d in sorted(plan, key=lambda value: (value['order_date'], value['file'])):
                representative.setdefault(d['owner_key'], d)

            PRIVATE_ROOT.mkdir(mode=0o700, parents=True, exist_ok=True)
            os.chmod(PRIVATE_ROOT, 0o700)
            private_csv = PRIVATE_ROOT / ('akun_skhp_2025_' + dt.datetime.now().strftime('%Y%m%d_%H%M%S') + '.csv.tmp')
            with private_csv.open('w', newline='', encoding='utf-8') as handle:
                writer = csv.writer(handle)
                writer.writerow(['Nama pemilik','ID masuk','Kata sandi awal'])
                for key in new_owner_keys:
                    doc = representative[key]
                    number = len(credentials) + 1
                    email = f'skhp{number:03d}@metrologi'
                    while email in email_set:
                        number += 1
                        email = f'skhp{number:03d}@metrologi'
                    email_set.add(email)
                    credentials.append((key, doc['owner'], email, plain[key]))
                    writer.writerow([doc['owner'], email, plain[key]])
            os.chmod(private_csv, 0o600)

            login_by_key = {key: email for key, _owner, email, _pass in credentials}
            for key in owners:
                if key in account_map:
                    continue
                matches = existing_names.get(key, [])
                if len(matches) > 1:
                    raise RuntimeError('Ada beberapa akun lama untuk pemilik yang sama: ' + key)
                if matches:
                    user_id = matches[0]
                    counts['accounts_reused'] += 1
                else:
                    doc = representative[key]
                    user_id = insert(cur, 'users', {'email': login_by_key[key], 'phone': None,
                        'password_hash': hashed[key], 'full_name': doc['owner'],
                        'account_type': 'applicant', 'account_status': 'active',
                        'phone_verification_required': 0})
                    insert(cur, 'user_roles', {'user_id': user_id,
                                              'role_id': applicant_role['id']})
                    district, village = region(doc['address'], districts, villages)
                    profile_id = insert(cur, 'applicant_profiles', {'user_id': user_id,
                        'profile_kind': owner_kind(doc['owner']),
                        'organization_name': doc['owner'] if owner_kind(doc['owner']) == 'organization' else None,
                        'contact_person': None, 'address': doc['address'],
                        'district': district['district_name'] if district else None,
                        'district_id': district['id'] if district else None,
                        'village': village['village_name'] if village else None,
                        'village_id': village['id'] if village else None,
                        'profile_status': 'unverified'})
                    profiles[user_id] = {'id': profile_id, 'user_id': user_id,
                                         'organization_name': doc['owner'], 'address': doc['address']}
                    counts['accounts_created'] += 1
                if user_id not in profiles:
                    raise RuntimeError('Akun tidak memiliki profil pemohon: ' + key)
                insert(cur, 'legacy_skhp_accounts', {'owner_key': key, 'user_id': user_id})
                account_map[key] = user_id

            request_map = {}
            existing_assets = defaultdict(dict)
            for asset in fetch_all(cur, '''SELECT a.id,a.applicant_profile_id,t.type_code,a.brand,
                    a.type_name,a.capacity,a.serial_number,a.service_address
                    FROM applicant_uttp a JOIN uttp_types t ON t.id=a.uttp_type_id
                    WHERE a.serial_number IS NOT NULL AND TRIM(a.serial_number)<>%s''', ('',)):
                profile = asset['applicant_profile_id']
                serial = simple(asset['serial_number'])
                address = simple(asset['service_address'])
                base = ('serial', profile, asset['type_code'], serial)
                existing_assets[profile][(*base,
                    simple((asset['type_name'] or '') + '|' + (asset['capacity'] or '')), address)] = asset['id']
                existing_assets[profile][(*base, simple(asset['brand']), address)] = asset['id']
            existing_certs = {r['certificate_no'] for r in fetch_all(cur,
                              'SELECT certificate_no FROM certificates')}
            primary_cert_file = {}
            for doc in plan:
                cert = doc['certificate_no']
                score = 1 if doc['book_company'] and doc['book_order_no'] else 0
                if cert not in primary_cert_file or score > primary_cert_file[cert][0]:
                    primary_cert_file[cert] = (score, doc['file'])

            for group_key, docs in grouped:
                if not any(d['file'] in {p['file'] for p in pending} for d in docs):
                    continue
                doc = min(docs, key=lambda item: (item['order_date'], item['file']))
                profile_id = profiles[account_map[doc['owner_key']]]['id']
                request_no = 'SKHP25/' + doc['order_no'] + '/' + hashlib.sha1(doc['owner_key'].encode()).hexdigest()[:6].upper()
                existing = fetch_one(cur, 'SELECT id,applicant_profile_id,submission_source FROM service_requests WHERE request_no=%s', (request_no,))
                if existing:
                    if existing['submission_source'] != 'historical' or existing['applicant_profile_id'] != profile_id:
                        raise RuntimeError('Nomor arsip berbenturan dengan permohonan lain: ' + request_no)
                    request_id = existing['id']
                else:
                    test_modes = {x['service_mode'] for x in docs}
                    mode = 'field' if 'field' in test_modes else 'office'
                    main_tester = Counter(x['tester'] for x in docs).most_common(1)[0][0]
                    tester_upper = main_tester.upper()
                    penera_id = (penera['APRILIA_FONI'] if 'APRILIA' in tester_upper else
                                 penera['SUSILO_ADI'] if 'SUSILO' in tester_upper else
                                 penera['MULAT_PARIPIH'] if 'MULAT' in tester_upper else None)
                    district, village = region(doc['address'], districts, villages)
                    note = ('Arsip pelayanan SKHP tahun 2025. Nomor order pada dokumen: '
                            + doc['reported_order_no'] + '. Rincian verifikasi dan kontak tidak tersedia dalam arsip.')
                    if len(test_modes) > 1:
                        note += ' Dokumen dalam order ini mencatat lokasi kantor dan luar kantor.'
                    request_id = insert(cur, 'service_requests', {'request_no': request_no,
                        'applicant_profile_id': profile_id, 'submitted_by_user_id': None,
                        'submission_source': 'historical', 'service_type': 'tera_ulang',
                        'request_status': 'closed', 'service_mode': mode,
                        'submitted_at': timestamp(min(x['order_date'] for x in docs)),
                        'completed_at': timestamp(max(x['issued_at'] for x in docs)),
                        'contact_name': None, 'contact_phone': None,
                        'service_address': doc['address'], 'village': village['village_name'] if village else None,
                        'district': district['district_name'] if district else None,
                        'village_id': village['id'] if village else None,
                        'district_id': district['id'] if district else None,
                        'applicant_note': note, 'penera_name': main_tester,
                        'penera_option_id': penera_id,
                        'created_at': timestamp(min(x['order_date'] for x in docs))})
                    insert(cur, 'request_status_history', {'service_request_id': request_id,
                        'from_status': None, 'to_status': 'closed', 'actor_user_id': None,
                        'note': 'Riwayat pelayanan diimpor dari SKHP 2025.',
                        'created_at': timestamp(max(x['issued_at'] for x in docs))})
                    counts['requests_created'] += 1
                request_map[group_key] = request_id

            www = pwd.getpwnam('www')
            for doc in plan:
                if doc['file'] in existing_files:
                    continue
                request_id = request_map[doc['request_group']]
                profile_id = profiles[account_map[doc['owner_key']]]['id']
                current = fetch_one(cur, 'SELECT COALESCE(MAX(unit_sequence),0) AS n FROM request_items WHERE service_request_id=%s', (request_id,))['n']
                duplicates = Counter((simple(u['serial']), simple(u['type_name']),
                                      simple(u['capacity'])) for u in doc['units'])
                first_item = None
                first_document = None
                for unit in doc['units']:
                    stamp = (simple(unit['serial']), simple(unit['type_name']), simple(unit['capacity']))
                    key = asset_key(profile_id, doc, unit, duplicates[stamp] > 1)
                    if key not in existing_assets[profile_id]:
                        type_id = types[doc['type_code']]['id']
                        asset_id = insert(cur, 'applicant_uttp', {'applicant_profile_id': profile_id,
                            'uttp_type_id': type_id, 'owner_name': doc['owner'],
                            'brand': unit['brand'] or 'Tidak tercantum',
                            'model_name': unit['model'] or None,
                            'type_name': unit['type_name'] or None,
                            'capacity': unit['capacity'] or None,
                            'serial_number': unit['serial'] or None,
                            'service_address': doc['address']})
                        existing_assets[profile_id][key] = asset_id
                        counts['assets_created'] += 1
                    else:
                        asset_id = existing_assets[profile_id][key]
                        counts['assets_reused'] += 1
                    current += 1
                    note = 'Arsip SKHP 2025: ' + doc['certificate_no'] + '; unit lampiran nomor ' + str(unit['row']) + '.'
                    if unit['label']:
                        note += ' ' + unit['label'] + '.'
                    note += ' Penguji: ' + doc['tester'] + '.'
                    item_id = insert(cur, 'request_items', {'service_request_id': request_id,
                        'unit_sequence': current, 'applicant_uttp_id': asset_id,
                        'uttp_type_id': types[doc['type_code']]['id'], 'item_status': 'valid',
                        'verification_decision': None, 'tested_at': doc['tested_at'],
                        'owner_name': doc['owner'], 'brand': unit['brand'] or 'Tidak tercantum',
                        'type_name': unit['type_name'] or None, 'model_name': unit['model'] or None,
                        'capacity': unit['capacity'] or None, 'serial_number': unit['serial'] or None,
                        'applicant_note': note, 'service_address': doc['address'],
                        'created_at': timestamp(doc['order_date']),
                        'updated_at': timestamp(doc['issued_at'])})
                    insert(cur, 'item_status_history', {'request_item_id': item_id,
                        'from_status': None, 'to_status': 'valid', 'actor_user_id': None,
                        'note': 'Hasil sah menurut arsip ' + doc['certificate_no'] + '.',
                        'created_at': timestamp(doc['issued_at'])})
                    counts['items_created'] += 1
                    if first_item is None:
                        first_item = item_id
                    if doc['type_code'] == 'PUBBM' and unit['row'] > 1:
                        continue
                    source = Path(manifest[doc['file']])
                    storage_key = secrets.token_hex(24) + '.pdf'
                    destination = DOC_ROOT / storage_key
                    shutil.copyfile(source, destination)
                    os.chmod(destination, 0o640)
                    os.chown(destination, www.pw_uid, www.pw_gid)
                    copies.append(destination)
                    document_id = insert(cur, 'service_documents', {
                        'service_request_id': request_id, 'request_item_id': item_id,
                        'document_type': 'skhp', 'visibility': 'applicant',
                        'original_name': document_name(doc), 'storage_key': storage_key,
                        'detected_mime': 'application/pdf', 'file_size': source.stat().st_size,
                        'sha256': hashlib.sha256(source.read_bytes()).hexdigest(),
                        'uploaded_by': None, 'is_current': 1})
                    counts['documents_created'] += 1
                    if first_document is None:
                        first_document = document_id
                if first_document is None:
                    raise RuntimeError('PDF SKHP tidak terhubung: ' + doc['file'])
                if (primary_cert_file[doc['certificate_no']][1] == doc['file']
                        and doc['certificate_no'] not in existing_certs):
                    insert(cur, 'certificates', {'request_item_id': first_item,
                        'service_document_id': first_document,
                        'certificate_no': doc['certificate_no'],
                        'certificate_version': 1,
                        'issued_at': timestamp(doc['issued_at']),
                        'issued_by': None, 'certificate_status': 'historical',
                        'verification_token': secrets.token_hex(32)})
                    existing_certs.add(doc['certificate_no'])
                    counts['certificates_created'] += 1
                insert(cur, 'legacy_skhp_files', {'source_file': doc['file'],
                    'certificate_no': doc['certificate_no'],
                    'reported_order_no': doc['reported_order_no'],
                    'effective_order_no': doc['order_no'],
                    'service_request_id': request_id,
                    'first_document_id': first_document,
                    'unit_count': len(doc['units']),
                    'source_note': source_note(doc, certificate_owners)})
                counts['files_imported'] += 1

            insert(cur, 'audit_logs', {'actor_user_id': None,
                'action_code': 'legacy_skhp_2025_import', 'entity_type': 'legacy_skhp_files',
                'entity_id': None,
                'summary': (f"Impor {counts['files_imported']} SKHP, {counts['accounts_created']} akun, "
                            f"{counts['requests_created']} order, {counts['items_created']} unit.")})
        if simulate:
            connection.rollback()
            for file in copies:
                file.unlink(missing_ok=True)
            if private_csv and private_csv.exists():
                private_csv.unlink()
            print('Simulasi berhasil; semua perubahan dibatalkan:', dict(counts))
            return
        connection.commit()
        committed = True
        final_csv = private_csv.with_suffix('')
        private_csv.rename(final_csv)
        os.chmod(final_csv, 0o600)
        print('Selesai:', dict(counts))
        print('Daftar akun baru (privat):', final_csv)
    except Exception:
        if not committed:
            connection.rollback()
            for file in copies:
                file.unlink(missing_ok=True)
            if private_csv and private_csv.exists():
                private_csv.unlink()
        raise
    finally:
        connection.close()


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument('--dry-run', action='store_true')
    group.add_argument('--simulate', action='store_true')
    group.add_argument('--apply', action='store_true')
    args = parser.parse_args()
    run(args.dry_run, args.simulate)

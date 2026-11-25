#!/usr/bin/env python3
"""Sinkronkan data PUBBM dari spreadsheet resmi; aman diulang dengan --apply."""
import argparse,csv,hashlib,json,re,subprocess
from pathlib import Path
import pymysql

ROOT=Path(__file__).resolve().parents[1]
CSV=ROOT/'database/source_spbu_2026_11_25.csv'
DOC=ROOT/'docs/task/akun-spbu-terbaru.md'
CODE=re.compile(r'(?:\d{2}\.\d{3}\.\d{2}|\d{2}\.\d\.\d\.\d{3}|4P\.\d{3}\.\d{2})',re.I)
PASSWORD='metrologi26'
LOCATION_TYPE=re.compile(r'^(SPBU|SPBKB|SPDN|SPBUN|PS)\b',re.I)
def station_username(group):
 location_type=LOCATION_TYPE.match(group['name'])
 if not location_type: raise ValueError('Jenis lokasi tidak terbaca: '+group['name'])
 return location_type.group(1).lower()+'.'+group['code'].lower()
def key(s): return re.sub('[^A-Z0-9]+','',s.upper())
def connect():
 c=json.loads(subprocess.check_output(['php','-r','echo json_encode(require "/etc/metro/database.php");']))
 return pymysql.connect(host='localhost',user=c['username'],password=c['password'],database='metro_app',charset='utf8mb4',autocommit=False,cursorclass=pymysql.cursors.DictCursor)
def source_rows(path):
 groups=[]; group=None
 for row in csv.DictReader(path.open(encoding='utf-8-sig')):
  if row['Nama'].strip():
   name=row['Nama'].strip(); match=CODE.search(name)
   if not match: raise ValueError('Kode tidak terbaca: '+name)
   group={'name':name,'code':match.group().upper(),'address':row['Alamat'].strip(),'rows':[]}
   groups.append(group)
  if not group: raise ValueError('Baris tanpa lokasi')
  if row['Nozzle'].strip(): group['rows'].append(row)
 return groups

def main():
 parser=argparse.ArgumentParser(); parser.add_argument('--apply',action='store_true');
 parser.add_argument('--csv',type=Path,default=CSV,help='CSV ekspor spreadsheet terbaru');args=parser.parse_args()
 groups=source_rows(args.csv)
 source_usernames=[station_username(g) for g in groups]
 if len(set(source_usernames))!=len(source_usernames): raise ValueError('Kode lokasi duplikat di CSV')
 db=connect(); cur=db.cursor()
 cur.execute("SELECT p.*,u.username,u.id AS uid FROM applicant_profiles p JOIN users u ON u.id=p.user_id WHERE p.organization_name IS NOT NULL")
 profiles=cur.fetchall()
 cur.execute('SELECT id,district_name FROM master_districts'); districts=cur.fetchall()
 cur.execute('SELECT id,district_id,village_name FROM master_villages'); villages=cur.fetchall()
 cur.execute("SELECT id FROM uttp_types WHERE type_code='PUBBM'"); type_id=cur.fetchone()['id']
 cur.execute("SELECT id FROM roles WHERE role_code='applicant'"); role_id=cur.fetchone()['id']
 existing_usernames=set()
 cur.execute('SELECT username FROM users WHERE username IS NOT NULL')
 existing_usernames={r['username'] for r in cur.fetchall()}
 planned=[]; matches=[]; inserts=0; updates=0; missing=0
 for g in groups:
  found=[p for p in profiles if (match:=CODE.search(p['organization_name'] or ''))
         and match.group().upper()==g['code']
         and (location_type:=LOCATION_TYPE.match(p['organization_name'] or ''))
         and location_type.group(1).lower()==LOCATION_TYPE.match(g['name']).group(1).lower()]
  if len(found)>1: raise ValueError('Akun ganda '+g['code'])
  p=found[0] if found else None
  if p:
   cur.execute('SELECT 1 FROM spbu_import_profiles WHERE applicant_profile_id=%s',(p['id'],))
   previously_imported=cur.fetchone() is not None
  else: previously_imported=False
  name_part=g['name'].split(',',1)[1].strip() if ',' in g['name'] else g['name']
  username=p['username'] if previously_imported and p['username'] else station_username(g)
  if p and p['username']==username: pass
  elif username in existing_usernames: raise ValueError('Username bentrok '+username)
  existing_usernames.add(username)
  address=g['address']; address_full=address if len(address)>=10 else address+', Kabupaten Rembang'
  district=None; village=None
  m=re.search(r'Kec(?:amatan)?\.?\s*([^,]+)',address,re.I)
  if m:
   district_name='Kaliori' if key(m.group(1))=='KALIRORI' else m.group(1)
   district=next((d for d in districts if key(d['district_name'])==key(district_name)),None)
  if not district:
   district=next((d for d in districts if key(d['district_name'])==key(name_part)),None)
  if district:
   first=re.split(r',|\bKec\b',address,flags=re.I)[0].strip()
   first=re.sub(r'^(?:Desa|Ds\.)\s*','',first,flags=re.I)
   village=next((v for v in villages if v['district_id']==district['id'] and key(v['village_name'])==key(first)),None)
   # Candisari adalah dusun; daftar ESDM menempatkan 44.592.14 di Desa Pamotan.
   if not village and g['code']=='44.592.14':
    village=next((v for v in villages if v['district_id']==district['id'] and key(v['village_name'])=='PAMOTAN'),None)
  planned.append((g,username,p,district,village,address_full,previously_imported))
  matches.append((g['code'],bool(p),len(g['rows'])))
 print('Lokasi',len(groups),'baru',sum(not x[2] for x in planned),'nozzle',sum(len(x['rows']) for x in groups))
 if not args.apply:
  for code,exists,n in matches: print(code,'existing' if exists else 'NEW',n)
  db.rollback(); return
 try:
  initialized=[]
  for g,username,p,district,village,address,previously_imported in planned:
   if not p:
    password_hash=subprocess.check_output(['php','-r','echo password_hash($argv[1], PASSWORD_DEFAULT);',PASSWORD],text=True).strip()
    cur.execute("INSERT INTO users(username,email,phone,password_hash,full_name,account_type,account_status,phone_verification_required) VALUES(%s,NULL,NULL,%s,%s,'applicant','active',0)",(username,password_hash,g['name']))
    uid=cur.lastrowid
    cur.execute('INSERT INTO user_roles(user_id,role_id) VALUES(%s,%s)',(uid,role_id))
    cur.execute("INSERT INTO applicant_profiles(user_id,profile_kind,organization_name,contact_person,address,district_id,district,village_id,village,profile_status) VALUES(%s,'organization',%s,%s,%s,%s,%s,%s,%s,'verified')",(uid,g['name'],name_part_from(g['name']),address,district['id'] if district else None,district['district_name'] if district else None,village['id'] if village else None,village['village_name'] if village else None))
    pid=cur.lastrowid; missing+=1
    initialized.append((g,username))
    lat=lng=loc=None
   else:
    uid=p['uid']; pid=p['id']; lat=p['latitude']; lng=p['longitude']; loc=p['location_source']
    if not previously_imported:
     password_hash=subprocess.check_output(['php','-r','echo password_hash($argv[1], PASSWORD_DEFAULT);',PASSWORD],text=True).strip()
     cur.execute("UPDATE users SET username=%s,password_hash=%s,account_status='active',phone_verification_required=0,updated_at=NOW() WHERE id=%s",(username,password_hash,uid))
     initialized.append((g,username))
    cur.execute('UPDATE applicant_profiles SET organization_name=%s,updated_at=NOW() WHERE id=%s',(g['name'],pid))
    if district and (not p['district_id'] or not p['village_id']):
     cur.execute('UPDATE applicant_profiles SET district_id=%s,district=%s,village_id=COALESCE(village_id,%s),village=COALESCE(village,%s),updated_at=NOW() WHERE id=%s',
       (district['id'],district['district_name'],village['id'] if village else None,village['village_name'] if village else None,pid))
   # Arsip lama tetap tersimpan. UTTP yang ditambahkan manual setelah impor tidak disentuh.
   first_import=not previously_imported
   if first_import:
    cur.execute('UPDATE applicant_uttp SET is_active=0,updated_at=NOW() WHERE applicant_profile_id=%s AND uttp_type_id IN (10,48) AND is_active=1 AND source_reference IS NULL',(pid,))
   cur.execute("UPDATE applicant_uttp SET is_active=0,updated_at=NOW() WHERE applicant_profile_id=%s AND source_reference LIKE 'spbu2026:%%' AND is_active=1",(pid,))
   for row in g['rows']:
    ref='spbu2026:'+hashlib.sha1('|'.join([g['code'],row['Nozzle'].strip(),row['Dispenser'].strip(),row['Media'].strip()]).encode()).hexdigest()[:32]
    vals=(pid,type_id,g['name'],row['Merk'].strip() or None,row['Tipe'].strip() or None,row['No. Seri'].strip() or None,row['Nozzle'].strip(),row['Dispenser'].strip(),row['Media'].strip(),row['Tahun pembuatan'].strip() or None,row['Qmax/Qmin'].strip() or None,ref,address,lat,lng,loc)
    cur.execute('''INSERT INTO applicant_uttp(applicant_profile_id,uttp_type_id,owner_name,brand,type_name,serial_number,nozzle_no,dispenser_no,media,manufacture_year,flow_rate,source_reference,service_address,latitude,longitude,location_source,is_active) VALUES(%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,1)
       ON DUPLICATE KEY UPDATE owner_name=VALUES(owner_name),brand=VALUES(brand),type_name=VALUES(type_name),serial_number=VALUES(serial_number),nozzle_no=VALUES(nozzle_no),dispenser_no=VALUES(dispenser_no),media=VALUES(media),manufacture_year=VALUES(manufacture_year),flow_rate=VALUES(flow_rate),service_address=VALUES(service_address),is_active=1,updated_at=NOW()''',vals)
    if cur.rowcount==1: inserts+=1
    else: updates+=1
   cur.execute('INSERT INTO spbu_import_profiles(applicant_profile_id,source_key) VALUES(%s,%s) ON DUPLICATE KEY UPDATE source_key=VALUES(source_key)',(pid,g['code']))
  current_codes={g['code'] for g in groups}
  for old in profiles:
   match=CODE.search(old['organization_name'] or '')
   if match and match.group().upper() not in current_codes and re.match(r'^(?:SPBU|SPBKB|SPDN|SPBUN|PS)\b',old['organization_name'] or '',re.I):
    cur.execute('SELECT 1 FROM spbu_import_profiles WHERE applicant_profile_id=%s',(old['id'],))
    if cur.fetchone() is None:
     cur.execute('UPDATE applicant_uttp SET is_active=0,updated_at=NOW() WHERE applicant_profile_id=%s AND uttp_type_id IN (10,48) AND is_active=1 AND source_reference IS NULL',(old['id'],))
    cur.execute("UPDATE applicant_uttp SET is_active=0,updated_at=NOW() WHERE applicant_profile_id=%s AND source_reference LIKE 'spbu2026:%%' AND is_active=1",(old['id'],))
    cur.execute('INSERT INTO spbu_import_profiles(applicant_profile_id,source_key) VALUES(%s,%s) ON DUPLICATE KEY UPDATE source_key=VALUES(source_key)',(old['id'],match.group().upper()))
  lines=['# Akun SPBU, SPBKB, SPDN, SPBUN, dan Pertashop','',
   'Sumber: spreadsheet master SPBU terbaru, gid 0. Sinkronisasi 25 November 2026.','',
   'Seluruh akun di bawah memakai kata sandi awal yang sama. Setelah masuk, pemilik akun dapat menggantinya di menu keamanan.','',
   '| Lokasi | Username | Kata sandi awal | Rincian nozzle |','|---|---|---|---:|']
  for g,username,p,_,_,_,_ in planned:
   lines.append(f"| {g['name']} | `{username}` | `{PASSWORD}` | {len(g['rows'])} |")
  lines+=['','Delapan lokasi yang belum mempunyai rincian nozzle tetap dibuatkan akun. Pemilik atau petugas dapat menambah nozzle pada menu UTTP.',
   '', 'Catatan wilayah: spreadsheet menulis Candisari untuk SPBU 44.592.14. Candisari adalah dusun; master desa dipetakan ke Desa Pamotan berdasarkan daftar ESDM yang menyebut kode 44.592.14 di Desa Pamotan: https://migas.esdm.go.id/cms/uploads/informasi-publik/Sub%20Penyalur%20LPG%203%20KG/Daftar%20Sub%20Penyalur%20LPG%203%20Kg%202025%20-%20September/Jawa%20Tengah%20-%20List%20Subpenyalur%20LPG%20Tabung%203%20Kg%20September%202025.pdf',
   '', 'Dua akun SPBUN arsip (48.592.04 dan 48.592.06) tidak ada di spreadsheet baru. Akunnya tetap untuk akses riwayat, sementara empat UTTP lama dinonaktifkan dari daftar alat aktif.', '']
  if not DOC.exists() or len(initialized)==len(groups): DOC.write_text('\n'.join(lines),encoding='utf-8')
  elif initialized:
   with DOC.open('a',encoding='utf-8') as output:
    output.write('\n\n## Akun tambahan dari sinkronisasi berikutnya\n\n| Lokasi | Username | Kata sandi awal | Rincian nozzle |\n|---|---|---|---:|\n')
    for g,username in initialized:
     output.write(f"| {g['name']} | `{username}` | `{PASSWORD}` | {len(g['rows'])} |\n")
  DOC.chmod(0o600)
  db.commit()
  print('Applied: new accounts',missing,'nozzle inserts',inserts,'upserts',updates,'credentials:',DOC)
 except Exception:
  db.rollback(); raise

def name_part_from(name): return name.split(',',1)[1].strip() if ',' in name else name
if __name__=='__main__': main()

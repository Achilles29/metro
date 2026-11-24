import json
import re
from collections import Counter, defaultdict
from io import BytesIO
from pathlib import Path
from zipfile import ZipFile

from openpyxl import load_workbook
from striprtf.striprtf import rtf_to_text

ZIP = Path('/www/wwwroot/metro/docs/source/SKHP 2025.zip')
OUT = Path('/tmp/metro-skhp-analysis.json')


def clean(value):
    return re.sub(r'\s+', ' ', value or '').strip(' |\t\r\n')


def field(text, label):
    match = re.search(label + r'.{0,100}?\|:\|([^|]*)', text, re.I | re.S)
    return clean(match.group(1)) if match else ''


with ZipFile(ZIP) as archive:
    book_name = next(n for n in archive.namelist() if 'BUKU ORDER' in n and n.endswith('.xlsx') and '~$' not in n)
    book = load_workbook(BytesIO(archive.read(book_name)), read_only=True, data_only=True).active
    orders = []
    for row in list(book.values)[1:]:
        if not isinstance(row[0], int):
            continue
        orders.append({
            'no': row[0], 'order_date': str(row[1].date()) if hasattr(row[1], 'date') else str(row[1] or ''),
            'order_no': clean(str(row[2] or '')), 'company': clean(str(row[3] or '')),
            'certificate_no': clean(str(row[4] or '')), 'type_code': clean(str(row[5] or '')),
            'count': row[6], 'tested_at': str(row[7].date()) if hasattr(row[7], 'date') else str(row[7] or ''),
            'note': clean(str(row[8] or '')) + ' ' + clean(str(row[9] or '')),
        })
    docs = []
    names = [n for n in archive.namelist() if n.lower().endswith('.rtf')]
    for index, name in enumerate(names, 1):
        source = archive.read(name).decode('latin1', errors='ignore')
        small = re.sub(r'(?:[0-9a-fA-F][0-9a-fA-F][\r\n]*){500,}', '', source)
        text = rtf_to_text(small)
        skhp = re.search(r'Nomor\s*:\s*([0-9. ]+/\s*[0-9A-Za-z ]+/\s*SKHP\s*/\s*2025)', text, re.I)
        order_no = re.search(r'No\.\s*Order\s*:\|\s*([^|\n]+)', text, re.I)
        result = field(text, 'KETERANGAN HASIL PENGUJIAN.*?Result of Verification is valid')
        item = {
            'file': name, 'filename_no': int(re.match(r'^(\d+)', Path(name).name).group(1)) if re.match(r'^(\d+)', Path(name).name) else None,
            'certificate_no': clean(skhp.group(1)) if skhp else '',
            'order_no': clean(order_no.group(1)) if order_no else '',
            'company': field(text, 'Nama Pemilik/Pemakai.*?Owner/User'),
            'address': field(text, r'Alamat.*?Address'),
            'instrument': field(text, 'NAMA ALAT.*?Measuring instrument'),
            'brand': field(text, r'Merek\s*/\s*Buatan.*?Trade Mark\s*/\s*Manufactured by'),
            'model': field(text, r'Model\s*/\s*Tipe.*?Model\s*/\s*Type'),
            'serial': field(text, r'Nomor Seri.*?Serial Number'),
            'capacity': field(text, r'Kapasitas\s*/\s*Massa Nominal.*?Capacity\s*/\s*Nominal Mass'),
            'test_date': field(text, r'TANGGAL PENGUJIAN.*?Test Date'),
            'result': result, 'raw_text': text,
        }
        docs.append(item)
        if index % 50 == 0:
            print('Parsed RTF', index, '/', len(names), flush=True)

OUT.write_text(json.dumps({'orders': orders, 'docs': docs}, ensure_ascii=False, indent=1))
OUT.chmod(0o600)
print('Orders', len(orders), 'RTF', len(docs))
print('Missing fields:', {k: sum(not d[k] for d in docs) for k in ['certificate_no','order_no','company','address','instrument','brand','serial','test_date','result']})
print('Instrument types:', Counter(d['instrument'] for d in docs).most_common(30))
print('Result values:', Counter(d['result'][:50] for d in docs).most_common(12))
print('Workbook codes:', Counter(o['type_code'] for o in orders))
print('Unique owner names RTF', len(set(d['company'].upper() for d in docs)))
print('Workbook unique certificate', len(set(o['certificate_no'] for o in orders)))
print('RTF filenames vs order missing', len(set(d['filename_no'] for d in docs)-set(o['no'] for o in orders)))
for d in docs:
    if any(not d[k] for k in ['certificate_no','order_no','company','address','instrument']):
        print('INCOMPLETE', d['file'], [(k, d[k]) for k in ['certificate_no','order_no','company','address','instrument']])

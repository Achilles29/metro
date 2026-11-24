import datetime
import hashlib
import json
import re
from collections import Counter, defaultdict
from pathlib import Path

SOURCE=Path('/tmp/metro-skhp-analysis.json')
OUT=Path('/tmp/metro-skhp-plan.json')
data=json.loads(SOURCE.read_text())
docs=[d for d in data['docs'] if d['company']]
orders=data['orders']


def clean(s):
    return re.sub(r'\s+',' ',str(s or '')).strip(' |')


def norm(s):
    return re.sub(r'[^A-Z0-9]','',clean(s).upper())


def owner_key(s):
    s=re.sub(r'\s*\(WAREHOUSE\)\s*$', '', clean(s),flags=re.I)
    return norm(s)


def cert_key(s):
    return re.sub(r'\s+','',s or '').upper()


def find(text,pattern):
    m=re.search(pattern,text,re.I|re.S)
    return clean(m.group(1)) if m else ''


MONTHS={'januari':1,'februari':2,'maret':3,'april':4,'mei':5,'juni':6,'juli':7,'agustus':8,
        'september':9,'oktober':10,'november':11,'desember':12}


def date(s):
    s=clean(s).replace('\u00a0',' ')
    if not s:return ''
    try:
        if re.fullmatch(r'\d{1,2}-\d{1,2}-\d{4}',s):return datetime.datetime.strptime(s,'%d-%m-%Y').date().isoformat()
        m=re.search(r'(\d{1,2})\s+([A-Za-z]+)\s+(20\d\d)',s)
        if m:return datetime.date(int(m.group(3)),MONTHS[m.group(2).lower()],int(m.group(1))).isoformat()
    except (ValueError,KeyError):pass
    return ''


def app_rows(text,header):
    i=text.find(header)
    if i<0:return []
    segment=text[i:]
    segment=re.split(r'(?m)^\s*Penguji,?\s*$',segment,maxsplit=1)[0]
    rows=[]
    lines=segment.splitlines()
    for k,line in enumerate(lines):
        m=re.match(r'^\s*(\d{1,2})\s*\|(.+)$',line)
        if not m:continue
        cells=[clean(x) for x in m.group(2).split('|')]
        if len(cells)<5:continue
        if header=='DATA POMPA':
            if len(cells)<6:continue
            label=cells[5]
            if k+1<len(lines):
                nextline=clean(lines[k+1]);
                if nextline and '|' in nextline and not re.match(r'^\d+\|',nextline):nextline=nextline.strip('|')
                if nextline and not re.match(r'^\d+\s*\|',nextline) and 'Penguji' not in nextline and len(nextline)<60:
                    label=clean(label+' '+nextline)
            rows.append({'row':int(m.group(1)),'brand':cells[0],'model':cells[1],
                         'serial':cells[2],'capacity':cells[4],'label':label})
        else:
            rows.append({'row':int(m.group(1)),'brand':cells[0],'model':cells[1],
                         'serial':cells[2],'capacity':cells[3],
                         'label':cells[4] if len(cells)>4 else ''})
    return rows


by_cert=defaultdict(list)
for o in orders:
    if o['certificate_no']:by_cert[cert_key(o['certificate_no'])].append(o)

output=[]
for d in docs:
    text=d['raw_text']
    certificate=find(text,r'Nomor\s*:\s*(510\.9\s*/\s*[0-9]+(?:\.[A-Z])?\s*/\s*SKHP\s*/\s*2025)')
    certificate=cert_key(certificate)
    matches=by_cert.get(certificate,[])
    book=next((o for o in matches if owner_key(o['company'])==owner_key(d['company'])
               or owner_key(d['company']) in owner_key(o['company'])
               or owner_key(o['company']) in owner_key(d['company'])
               or o['order_no']==d['order_no']),None)
    if book and certificate=='510.9/0128/SKHP/2025' and 'SADHANA' in d['company'].upper():book=None
    effective_order=(book['order_no'] if book and book['order_no'] else d['order_no'])
    instrument=d['instrument'].upper()
    type_code=('PUBBM' if instrument=='POMPA UKUR BBM' else
               'TIMBANGAN_ELEKTRONIK' if instrument=='TIMBANGAN ELEKTRONIK' else
               'TIMBANGAN_PEGAS' if instrument=='TIMBANGAN PEGAS' else
               'TIMBANGAN_JEMBATAN' if 'JEMBATAN' in instrument else
               'TIMBANGAN_SENTISIMAL' if 'SENTISIMAL' in instrument else
               'ANAK_TIMBANGAN' if instrument=='ANAK TIMBANGAN' else
               'WIM' if 'KENDARAAN BERGERAK' in instrument else
               'TIMBANGAN_BATCHING' if 'BATCHING PLANT' in instrument else
               'TIMBANGAN_AMP' if 'TIMBANGAN AMP' in instrument else
               'TIMBANGAN_ANALITIK' if 'TIMBANGAN ANALITIK' in instrument else '')
    rows=(app_rows(text,'DATA POMPA') if type_code=='PUBBM' else
          app_rows(text,'DATA TIMBANGAN') if 'BUAH' in instrument else [])
    if not rows:rows=[{'row':1,'brand':d['brand'],'model':d['model'],
                       'serial':d['serial'],'capacity':d['capacity'],'label':''}]
    for row in rows:
        for key in ['brand','model','serial','capacity']:
            if row[key].strip().lower() in ('-','terlampir','terlampir.','- / -',''):row[key]=''
        row['brand']=row['brand'].split(' / ')[0].strip()
        if type_code=='PUBBM':
            row['type_name']=clean(row['model']+' / '+row['label'])[:120]
        elif 'BUAH' in instrument:
            row['type_name']=clean(row['model']+' / '+row['label'])[:120]
        else:
            row['type_name']=clean(row['model'])[:120]
        row['brand']=row['brand'][:120]
        row['model']=row['model'][:120]
        row['capacity']=row['capacity'][:120]
        row['serial']=row['serial'][:120]
    order_date=date(find(text,r'No\.\s*Order\s*:\|\s*[^|\n]+\|.*?(\d{2}-\d{2}-\d{4})'))
    tested=date(find(text,r'TANGGAL PENGUJIAN\s*\nTest Date[^|]*\|:\|([^|]+)'))
    issued=date(find(text,r'Rembang,\s*(\d{1,2}\s+[A-Za-z]+\s+2025)'))
    location=find(text,r'- Lokasi\|:\|([^|]*)')
    tester=find(text,r'- Nama Penguji\|:\|([^|]*)')
    if not tester:tester=find(text,r'PENGUJI\s*\nTester\s*\|:\|([^|]*)').split('/ NIP')[0]
    owner=clean(re.sub(r'\s*\(WAREHOUSE\)\s*$', '', d['company'],flags=re.I))
    output.append({'file':d['file'],'certificate_no':certificate,'owner':owner,
                   'owner_key':owner_key(owner),'address':d['address'],'instrument':d['instrument'],
                   'type_code':type_code,'reported_order_no':d['order_no'],'order_no':effective_order,
                   'book_order_no':book['order_no'] if book else '',
                   'order_date':order_date or (book['order_date'] if book else ''),
                   'tested_at':tested or (book['tested_at'] if book else ''),
                   'issued_at':issued or tested,'tester':tester,'test_location':location,
                   'service_mode':'office' if 'KANTOR UPT' in location.upper() else 'field',
                   'units':rows,'book_count':book['count'] if book else None,
                   'book_company':book['company'] if book else '',
                   'request_group':owner_key(owner)+'|'+effective_order})

OUT.write_text(json.dumps(output,ensure_ascii=False,indent=1))
OUT.chmod(0o600)
print('Documents',len(output),'owners',len(set(d['owner_key'] for d in output)),
      'request groups',len(set(d['request_group'] for d in output)),
      'unit rows',sum(len(d['units']) for d in output))
print('Missing', {k:sum(not d[k] for d in output) for k in ['certificate_no','owner','address','type_code','order_no','order_date','tested_at','issued_at','tester']})
print('Modes',Counter(d['service_mode'] for d in output))
print('Testers',Counter(d['tester'] for d in output))
print('Type units',Counter((d['type_code'],len(d['units'])) for d in output).most_common(20))
print('Certificate duplicates',[(k,n) for k,n in Counter(d['certificate_no'] for d in output).items() if n>1])
print('Book count differences:',[(d['file'].split('/')[-1],len(d['units']),d['book_count']) for d in output if isinstance(d['book_count'],int) and d['book_count']!=len(d['units'])])
print('Order differences:',[(d['file'].split('/')[-1],d['reported_order_no'],d['book_order_no']) for d in output if d['book_order_no'] and d['reported_order_no']!=d['book_order_no']])
by_group=defaultdict(set)
for d in output:by_group[d['request_group']].add(d['service_mode'])
print('Mixed mode groups',[(k,list(v)) for k,v in by_group.items() if len(v)>1])

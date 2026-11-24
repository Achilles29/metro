'use strict';
const http = require('http');
const fs = require('fs');
const path = require('path');
const mysql = require('mysql2/promise');
const pino = require('pino');
const {default: makeWASocket, useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion} = require('@whiskeysockets/baileys');
const port = Number(process.env.WA_PORT || 3072);
const token = process.env.WA_TOKEN || '';
if (!token || !process.env.DB_USER || !process.env.DB_NAME) throw new Error('WA Engine configuration incomplete');
const pool = mysql.createPool({host: process.env.DB_HOST || '127.0.0.1', user: process.env.DB_USER,
  password: process.env.DB_PASS || '', database: process.env.DB_NAME, connectionLimit: 3});
const authPath = path.join(__dirname, 'auth_info');
let socket = null, status = 'DISABLED', phone = null, qr = null, enabled = false, busy = false, starting = false;
const sql = (query, params = []) => pool.query(query, params);
const reply = (res, code, value) => {res.writeHead(code, {'content-type':'application/json','cache-control':'no-store'});res.end(JSON.stringify(value));};
const body = req => new Promise(resolve => {let raw = '';req.on('data', chunk => {raw += chunk;if (raw.length > 1000) req.destroy();});req.on('end', () => {try {resolve(JSON.parse(raw || '{}'));} catch {resolve({});}});});
async function persist() {await sql('UPDATE wa_session SET is_enabled=?,status=?,phone_number=?,qr_data=?,last_ping_at=NOW() WHERE id=1', [enabled ? 1 : 0, status, phone, qr]);}
async function close() {const old = socket;socket = null;try {old?.ws?.close();} catch {}}
async function control(action) {
  if (action === 'disable') {enabled = false;status = 'DISABLED';qr = null;await close();await persist();return {ok:true,status,message:'Bot dinonaktifkan; sesi tertaut disimpan.'};}
  if (action === 'unlink') {enabled = false;try {await socket?.logout();} catch {} await close();fs.rmSync(authPath,{recursive:true,force:true});phone = null;qr = null;status = 'DISABLED';await persist();return {ok:true,status,message:'Nomor dilepas. Aktifkan bot untuk menautkan nomor baru.'};}
  if (action === 'enable') {enabled = true;status = 'CONNECTING';qr = null;await persist();await close();setTimeout(() => start().catch(e => console.error('WA start:',e.message)), 100);return {ok:true,status,message:'Bot diaktifkan. Muat QR dalam beberapa saat.'};}
  return {ok:false,message:'Aksi tidak dikenal.'};
}
http.createServer(async (req,res) => {
  try {
    const url = new URL(req.url,'http://127.0.0.1');
    if (url.searchParams.get('token') !== token) return reply(res,403,{ok:false,message:'Forbidden'});
    if (req.method === 'GET' && url.pathname === '/internal/status') return reply(res,200,{ok:true,status,phone,enabled});
    if (req.method === 'GET' && url.pathname === '/internal/qr') return reply(res,200,{ok:true,status,phone,enabled,qr:enabled ? qr : null});
    if (req.method === 'POST' && url.pathname === '/internal/control') return reply(res,200,await control((await body(req)).action));
    reply(res,404,{ok:false,message:'Not found'});
  } catch (error) {reply(res,500,{ok:false,message:'WA Engine gagal memproses permintaan.'});console.error('WA control:',error.message);}
}).listen(port,'127.0.0.1');
async function worker() {
  if (busy || !enabled || status !== 'CONNECTED' || !socket) return;
  busy = true;
  try {
    const [rows] = await sql("SELECT o.*,c.expires_at,c.verified_at,u.phone AS current_phone,u.phone_verified_at FROM wa_outbox o JOIN phone_verification_codes c ON c.id=o.code_id JOIN users u ON u.id=o.user_id WHERE o.status='PENDING' ORDER BY o.id LIMIT 5");
    for (const item of rows) {
      if (new Date(item.expires_at).getTime() <= Date.now() || item.verified_at || item.phone_verified_at || item.current_phone !== item.phone_number) {
        await sql("UPDATE wa_outbox SET status='CANCELLED',message='',error_message='Kode tidak lagi berlaku' WHERE id=? AND status='PENDING'",[item.id]);continue;
      }
      const [claim] = await sql("UPDATE wa_outbox SET status='SENDING',attempts=attempts+1 WHERE id=? AND status='PENDING'",[item.id]);
      if (claim.affectedRows !== 1) continue;
      try {
        const destination = String(item.phone_number).replace(/\D/g,'').replace(/^0/,'62') + '@s.whatsapp.net';
        await socket.sendMessage(destination,{text:item.message});
        await sql("UPDATE wa_outbox SET status='SENT',message='',sent_at=NOW(),error_message=NULL WHERE id=?",[item.id]);
      } catch (error) {
        await sql("UPDATE wa_outbox SET status='FAILED',error_message=? WHERE id=?",[String(error.message).slice(0,500),item.id]);
      }
      await new Promise(resolve => setTimeout(resolve,900));
    }
    const [notifications] = await sql("SELECT n.*,u.phone AS current_phone FROM wa_notifications n JOIN users u ON u.id=n.user_id WHERE n.status='PENDING' ORDER BY n.id LIMIT 5");
    for (const item of notifications) {
      if (item.current_phone !== item.phone_number || Date.now() - new Date(item.created_at).getTime() > 86400000) {
        await sql("UPDATE wa_notifications SET status='CANCELLED',message='',error_message='Nomor berubah atau pesan kedaluwarsa' WHERE id=? AND status='PENDING'",[item.id]);continue;
      }
      const [claim] = await sql("UPDATE wa_notifications SET status='SENDING',attempts=attempts+1 WHERE id=? AND status='PENDING'",[item.id]);
      if (claim.affectedRows !== 1) continue;
      try {
        const destination = String(item.phone_number).replace(/\D/g,'').replace(/^0/,'62') + '@s.whatsapp.net';
        await socket.sendMessage(destination,{text:item.message});
        await sql("UPDATE wa_notifications SET status='SENT',message='',sent_at=NOW(),error_message=NULL WHERE id=?",[item.id]);
      } catch (error) {
        await sql("UPDATE wa_notifications SET status='FAILED',error_message=? WHERE id=?",[String(error.message).slice(0,500),item.id]);
      }
      await new Promise(resolve => setTimeout(resolve,900));
    }
  } finally {busy = false;}
}
async function start() {
  if (!enabled || starting) return;
  starting = true;
  try {
    const {state,saveCreds} = await useMultiFileAuthState(authPath);
    const {version} = await fetchLatestBaileysVersion();
    socket = makeWASocket({version,auth:state,logger:pino({level:'silent'})});
    const currentSocket = socket;
    socket.ev.on('creds.update',saveCreds);
    socket.ev.on('connection.update',async update => {
      try {
        if (currentSocket !== socket) return;
        if (update.qr && enabled) {qr = update.qr;status = 'WAITING_QR';await persist();}
        if (update.connection === 'open' && enabled) {qr = null;phone = String(socket.user.id).split(':')[0];status = 'CONNECTED';await persist();}
        if (update.connection === 'close') {
          qr = null;status = enabled ? 'DISCONNECTED' : 'DISABLED';await persist();
          if (enabled && update.lastDisconnect?.error?.output?.statusCode !== DisconnectReason.loggedOut)
            setTimeout(() => start().catch(e => console.error('WA reconnect:',e.message)),5000);
        }
      } catch (error) {console.error('WA connection:',error.message);}
    });
  } finally {starting = false;}
}
async function boot() {
  const [rows] = await sql('SELECT is_enabled,phone_number FROM wa_session WHERE id=1');
  enabled = !!Number(rows[0]?.is_enabled);phone = rows[0]?.phone_number || null;
  status = enabled ? 'CONNECTING' : 'DISABLED';await persist();
  await sql("UPDATE wa_outbox SET status='FAILED',error_message='Pengiriman terhenti; coba lagi' WHERE status='SENDING'");
  await sql("UPDATE wa_notifications SET status='FAILED',error_message='Pengiriman terhenti; coba lagi' WHERE status='SENDING'");
  if (enabled) await start();
}
boot().catch(error => console.error('WA boot:',error.message));
setInterval(() => {if (enabled) worker().catch(error => console.error('WA worker:',error.message));},3000);
setInterval(() => persist().catch(error => console.error('WA heartbeat:',error.message)),10000);
setInterval(() => sql("UPDATE wa_outbox o JOIN phone_verification_codes c ON c.id=o.code_id SET o.status='CANCELLED',o.message='',o.error_message='Kode kedaluwarsa' WHERE c.expires_at<NOW() AND o.status IN ('PENDING','FAILED','SENDING')").catch(error => console.error('WA cleanup:',error.message)),60000);
setInterval(() => sql("UPDATE wa_notifications SET status='CANCELLED',message='',error_message='Pesan kedaluwarsa' WHERE created_at<DATE_SUB(NOW(), INTERVAL 1 DAY) AND status IN ('PENDING','FAILED')").catch(error => console.error('WA notification cleanup:',error.message)),60000);

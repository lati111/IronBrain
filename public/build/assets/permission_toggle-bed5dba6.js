import{p as i}from"./ajax-3bdbf024.js";async function e(a,o){const s=new FormData;a===!0?s.append("hasPermission","1"):s.append("hasPermission","0"),await i(o,s)}window.togglePermission=e;

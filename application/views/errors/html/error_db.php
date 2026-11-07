<!doctype html><html><head><meta charset="utf-8"><title><?php echo isset($heading) ? $heading : 'Error'; ?></title>
<style>body{font-family:system-ui,sans-serif;background:#f4f8fc;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}
.b{background:#fff;padding:2rem 3rem;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.08);max-width:520px}h1{color:#0b3d91;font-size:1.4rem}</style></head>
<body><div class="b"><h1><?php echo isset($heading) ? $heading : 'Terjadi kesalahan'; ?></h1><?php echo isset($message) ? $message : ''; ?></div></body></html>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e(($title ?? 'Hoops') . ' · ' . $appName) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            court: { 50:'#f4f7f2', 100:'#e4ecdf', 500:'#3d6b4f', 700:'#2a4a37', 900:'#1a2f24' },
            orange: { ball:'#e85d04' }
          },
          fontFamily: {
            display: ['"Archivo Black"', 'system-ui', 'sans-serif'],
            body: ['"DM Sans"', 'system-ui', 'sans-serif'],
          }
        }
      }
    }
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'DM Sans', system-ui, sans-serif; }
    .font-display { font-family: 'Archivo Black', system-ui, sans-serif; }
    .pb-safe { padding-bottom: max(5.5rem, env(safe-area-inset-bottom)); }
    .field-input { width:100%; border-radius:0.5rem; border:1px solid #cbd5e1; background:#fff; padding:0.75rem; font-size:1rem; }
  </style>
</head>
<body class="min-h-screen bg-court-50 text-court-900 antialiased">
  <div class="mx-auto flex min-h-screen max-w-3xl flex-col">
    <header class="sticky top-0 z-20 border-b border-court-100/80 bg-court-50/95 backdrop-blur">
      <div class="flex items-center justify-between px-4 py-3">
        <a href="<?= e(url('/')) ?>" class="font-display text-xl tracking-tight text-court-900">
          HOOPS<span class="text-orange-ball">.</span>
        </a>
        <div class="flex items-center gap-3 text-sm">
          <?php if ($activeSeason): ?>
            <span class="hidden text-court-500 sm:inline"><?= e($activeSeason['name']) ?></span>
          <?php endif; ?>
          <?php if ($currentUser): ?>
            <form method="post" action="<?= e(url('/logout')) ?>">
              <?= csrf_field() ?>
              <button type="submit" class="text-court-500 hover:text-court-900">Log out</button>
            </form>
          <?php else: ?>
            <a href="<?= e(url('/login')) ?>" class="font-medium text-orange-ball hover:text-orange-600">Log in</a>
          <?php endif; ?>
        </div>
      </div>
    </header>

    <?php if ($flashes): ?>
      <div class="space-y-2 px-4 pt-3">
        <?php foreach ($flashes as $flash): ?>
          <div class="rounded-lg px-3 py-2 text-sm <?= $flash['type'] === 'error' ? 'bg-red-100 text-red-800' : 'bg-court-100 text-court-700' ?>">
            <?= e($flash['message']) ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <main class="flex-1 px-4 py-4 pb-safe">
      <?php require $templateFile; ?>
    </main>

    <?php
      $path = request_path();
    ?>
    <nav class="fixed bottom-0 left-0 right-0 z-20 border-t border-court-100 bg-white/95 backdrop-blur"
         style="padding-bottom: env(safe-area-inset-bottom);">
      <div class="mx-auto grid max-w-3xl grid-cols-4 <?= $navIsCommissioner ? 'sm:grid-cols-5' : '' ?> text-center text-xs font-medium text-court-500">
        <a href="<?= e(url('/')) ?>" class="flex flex-col items-center gap-1 py-3 <?= $path === '/' ? 'text-orange-ball' : '' ?>">
          <span class="text-lg leading-none">⌂</span>Home
        </a>
        <a href="<?= e(url('/schedule')) ?>" class="flex flex-col items-center gap-1 py-3 <?= str_starts_with($path, '/schedule') || str_starts_with($path, '/games') ? 'text-orange-ball' : '' ?>">
          <span class="text-lg leading-none">☰</span>Schedule
        </a>
        <a href="<?= e(url('/standings')) ?>" class="flex flex-col items-center gap-1 py-3 <?= $path === '/standings' ? 'text-orange-ball' : '' ?>">
          <span class="text-lg leading-none">#</span>Standings
        </a>
        <a href="<?= e(url('/teams')) ?>" class="flex flex-col items-center gap-1 py-3 <?= str_starts_with($path, '/teams') ? 'text-orange-ball' : '' ?>">
          <span class="text-lg leading-none">◎</span>Teams
        </a>
        <?php if ($navIsCommissioner): ?>
          <a href="<?= e(url('/manage')) ?>" class="hidden flex-col items-center gap-1 py-3 sm:flex <?= str_starts_with($path, '/manage') ? 'text-orange-ball' : '' ?>">
            <span class="text-lg leading-none">⚙</span>Manage
          </a>
        <?php endif; ?>
      </div>
    </nav>
  </div>
</body>
</html>

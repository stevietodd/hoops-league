<p class="text-sm text-court-500">
  <a href="<?= e(url('/schedule')) ?>" class="hover:text-court-900">← Schedule</a>
</p>

<article class="mt-4 rounded-2xl border border-court-100 bg-white p-5 shadow-sm">
  <p class="text-xs font-medium uppercase tracking-wide text-court-400">
    <?php if (($game['phase'] ?? 'regular') === 'playoff'): ?>
      <span class="text-orange-ball">Playoff</span> ·
    <?php endif; ?>
    <?= e(format_tipoff($game['tipoff'], 'l, F j · g:i A')) ?>
  </p>
  <h1 class="mt-3 font-display text-2xl leading-tight sm:text-3xl">
    <?= e($game['left_name']) ?>
    <span class="text-court-400">vs</span>
    <?= e($game['right_name']) ?>
  </h1>
  <?php if (($game['phase'] ?? 'regular') === 'playoff' && ($game['left_seed'] !== null || $game['right_seed'] !== null)): ?>
    <p class="mt-2 text-sm text-court-500">
      <?php if ($game['left_seed'] !== null): ?>#<?= (int) $game['left_seed'] ?><?php endif; ?>
      <?php if ($game['left_seed'] !== null && $game['right_seed'] !== null): ?> vs <?php endif; ?>
      <?php if ($game['right_seed'] !== null): ?>#<?= (int) $game['right_seed'] ?><?php endif; ?>
    </p>
  <?php endif; ?>

  <?php if ($game['status'] === 'final' && $game['left_score'] !== null): ?>
    <div class="mt-6 flex items-end justify-center gap-6">
      <div class="text-center">
        <p class="font-display text-5xl <?= (int)$game['left_score'] > (int)$game['right_score'] ? 'text-court-900' : 'text-court-400' ?>">
          <?= (int) $game['left_score'] ?>
        </p>
      </div>
      <span class="pb-2 text-court-300">–</span>
      <div class="text-center">
        <p class="font-display text-5xl <?= (int)$game['right_score'] > (int)$game['left_score'] ? 'text-court-900' : 'text-court-400' ?>">
          <?= (int) $game['right_score'] ?>
        </p>
      </div>
    </div>
    <p class="mt-3 text-center text-xs uppercase tracking-wide text-court-400">Final</p>
  <?php else: ?>
    <p class="mt-6 rounded-lg bg-court-50 px-3 py-3 text-center text-sm text-court-500">Result not reported yet.</p>
  <?php endif; ?>
</article>

<div class="mt-4 flex flex-wrap gap-3">
  <?php if ($canReport): ?>
    <a href="<?= e(url('/games/' . $game['id'] . '/report')) ?>"
       class="rounded-lg bg-orange-ball px-4 py-3 text-sm font-semibold text-white">
      <?= $game['status'] === 'final' ? 'Edit score' : 'Report score' ?>
    </a>
  <?php endif; ?>
  <?php if ($navIsCommissioner): ?>
    <a href="<?= e(url('/manage/games/' . $game['id'] . '/edit')) ?>"
       class="rounded-lg border border-court-200 bg-white px-4 py-3 text-sm font-medium">Edit game</a>
  <?php endif; ?>
</div>

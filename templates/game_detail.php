<p class="text-sm text-court-500">
  <a href="<?= e(url('/schedule')) ?>" class="hover:text-court-900">← Schedule</a>
</p>

<article class="mt-4 rounded-2xl border border-court-100 bg-white p-5 shadow-sm">
  <p class="text-xs font-medium uppercase tracking-wide text-court-400">
    <?= e(format_tipoff($game['tipoff'], 'l, F j · g:i A')) ?>
  </p>
  <h1 class="mt-3 font-display text-2xl leading-tight sm:text-3xl">
    <?= e($game['away_name']) ?>
    <span class="text-court-400">@</span>
    <?= e($game['home_name']) ?>
  </h1>
  <?php if ($game['location']): ?>
    <p class="mt-2 text-court-500"><?= e($game['location']) ?></p>
  <?php endif; ?>

  <?php if ($game['status'] === 'final' && $game['home_score'] !== null): ?>
    <div class="mt-6 flex items-end justify-center gap-6">
      <div class="text-center">
        <p class="text-sm text-court-500"><?= e($game['away_abbrev'] ?: $game['away_name']) ?></p>
        <p class="font-display text-5xl <?= (int)$game['away_score'] > (int)$game['home_score'] ? 'text-court-900' : 'text-court-400' ?>">
          <?= (int) $game['away_score'] ?>
        </p>
      </div>
      <span class="pb-2 text-court-300">–</span>
      <div class="text-center">
        <p class="text-sm text-court-500"><?= e($game['home_abbrev'] ?: $game['home_name']) ?></p>
        <p class="font-display text-5xl <?= (int)$game['home_score'] > (int)$game['away_score'] ? 'text-court-900' : 'text-court-400' ?>">
          <?= (int) $game['home_score'] ?>
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

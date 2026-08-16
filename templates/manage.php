<h1 class="font-display text-3xl">Manage</h1>
<p class="mt-1 text-sm text-court-500"><?= $navIsAdmin ? 'Admin tools' : 'Commissioner tools' ?></p>

<div class="mt-6 grid gap-3 sm:grid-cols-2">
  <a href="<?= e(url('/manage/games/new')) ?>" class="rounded-xl border border-court-100 bg-white px-4 py-4 font-semibold shadow-sm">Add game</a>
  <?php if (empty($playoffTournament)): ?>
    <a href="<?= e(url('/manage/playoffs/new')) ?>" class="rounded-xl border border-court-100 bg-white px-4 py-4 font-semibold shadow-sm">Start playoffs</a>
  <?php else: ?>
    <a href="<?= e(url('/playoffs')) ?>" class="rounded-xl border border-court-100 bg-white px-4 py-4 font-semibold shadow-sm">View playoffs</a>
  <?php endif; ?>
  <?php if ($navIsAdmin): ?>
    <a href="<?= e(url('/manage/users')) ?>" class="rounded-xl border border-court-100 bg-white px-4 py-4 font-semibold shadow-sm">Manage commissioners</a>
  <?php endif; ?>
</div>

<section class="mt-8">
  <h2 class="font-display text-xl">Teams & captains</h2>
  <ul class="mt-3 space-y-2">
    <?php foreach ($teams as $team): ?>
      <li class="flex items-center justify-between rounded-lg border border-court-100 bg-white px-3 py-3">
        <div>
          <a href="<?= e(url('/teams/' . $team['id'])) ?>" class="font-medium"><?= e(team_label($team)) ?></a>
          <p class="text-xs text-court-500">
            Captain: <?= e($team['captain_display_name'] ?? '—') ?>
          </p>
        </div>
        <a href="<?= e(url('/teams/' . $team['id'])) ?>" class="text-sm text-orange-ball">Edit</a>
      </li>
    <?php endforeach; ?>
    <?php if (!$teams): ?>
      <li class="text-court-500">No teams yet.</li>
    <?php endif; ?>
  </ul>
</section>

<section class="mt-8">
  <div class="mb-3 flex items-end justify-between">
    <h2 class="font-display text-xl">Upcoming</h2>
    <a href="<?= e(url('/schedule')) ?>" class="text-sm text-orange-ball">Schedule</a>
  </div>
  <ul class="space-y-2">
    <?php foreach ($upcoming as $game): ?>
      <li class="flex items-center justify-between rounded-lg border border-court-100 bg-white px-3 py-3 text-sm">
        <span><?= e($game['away_abbrev']) ?> @ <?= e($game['home_abbrev']) ?> · <?= e(format_tipoff($game['tipoff'], 'M j, g:i A')) ?></span>
        <a href="<?= e(url('/manage/games/' . $game['id'] . '/edit')) ?>" class="text-orange-ball">Edit</a>
      </li>
    <?php endforeach; ?>
    <?php if (!$upcoming): ?>
      <li class="text-court-500">No upcoming games.</li>
    <?php endif; ?>
  </ul>
</section>

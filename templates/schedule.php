<div class="flex items-end justify-between gap-3">
  <div>
    <h1 class="font-display text-3xl">Schedule</h1>
    <p class="mt-1 text-sm text-court-500"><?= $season ? e($season['name']) : 'No active season' ?></p>
  </div>
  <?php if ($navIsCommissioner): ?>
    <a href="<?= e(url('/manage/games/new')) ?>" class="rounded-lg bg-orange-ball px-3 py-2 text-sm font-semibold text-white">Add game</a>
  <?php endif; ?>
</div>

<form method="get" class="mt-4">
  <label class="sr-only" for="team">Filter by team</label>
  <select name="team" id="team" class="field-input" onchange="this.form.submit()">
    <option value="">All teams</option>
    <?php foreach ($teams as $team): ?>
      <option value="<?= (int) $team['id'] ?>" <?= $selectedTeam === (int) $team['id'] ? 'selected' : '' ?>>
        <?= e(team_label($team)) ?>
      </option>
    <?php endforeach; ?>
  </select>
</form>

<?php if ($games): ?>
  <ul class="mt-6 space-y-3">
    <?php foreach ($games as $game): ?>
      <li>
        <a href="<?= e(url('/games/' . $game['id'])) ?>" class="block rounded-xl border border-court-100 bg-white px-4 py-3">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-xs font-medium uppercase tracking-wide text-court-400">
                <?php if (($game['phase'] ?? 'regular') === 'playoff'): ?>
                  <span class="text-orange-ball">Playoff</span> ·
                <?php endif; ?>
                <?= e(format_tipoff($game['tipoff'])) ?>
              </p>
              <p class="mt-1 font-semibold">
                <?= e($game['away_name']) ?>
                <span class="font-normal text-court-400">@</span>
                <?= e($game['home_name']) ?>
              </p>
              <?php if ($game['location']): ?>
                <p class="mt-1 text-sm text-court-500"><?= e($game['location']) ?></p>
              <?php endif; ?>
            </div>
            <div class="text-right">
              <?php if ($game['status'] === 'final' && $game['home_score'] !== null): ?>
                <p class="font-display text-lg leading-none"><?= (int) $game['away_score'] ?>–<?= (int) $game['home_score'] ?></p>
                <p class="mt-1 text-xs uppercase text-court-400">Final</p>
              <?php else: ?>
                <span class="rounded-full bg-court-100 px-2 py-1 text-xs text-court-600">Scheduled</span>
              <?php endif; ?>
            </div>
          </div>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
<?php else: ?>
  <p class="mt-8 rounded-xl border border-dashed border-court-200 px-4 py-8 text-center text-court-500">No games on the schedule yet.</p>
<?php endif; ?>

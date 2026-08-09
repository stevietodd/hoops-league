<h1 class="font-display text-3xl">Teams</h1>
<p class="mt-1 text-sm text-court-500">Rosters and captains</p>

<ul class="mt-6 space-y-3">
  <?php foreach ($teams as $team): ?>
    <li>
      <a href="<?= e(url('/teams/' . $team['id'])) ?>"
         class="flex items-center justify-between rounded-xl border border-court-100 bg-white px-4 py-4">
        <div class="flex items-center gap-3">
          <span class="flex h-10 w-10 items-center justify-center rounded-full bg-court-100 font-display text-sm"
                <?php if ($team['color']): ?>style="background: <?= e($team['color']) ?>22; color: <?= e($team['color']) ?>"<?php endif; ?>>
            <?= e(team_short($team)) ?>
          </span>
          <div>
            <p class="font-semibold"><?= e(team_label($team)) ?></p>
            <p class="text-sm text-court-500"><?= (int) $team['player_count'] ?> player<?= (int)$team['player_count'] === 1 ? '' : 's' ?></p>
          </div>
        </div>
        <span class="text-court-300">›</span>
      </a>
    </li>
  <?php endforeach; ?>
  <?php if (!$teams): ?>
    <li class="rounded-xl border border-dashed border-court-200 px-4 py-8 text-center text-court-500">No teams yet.</li>
  <?php endif; ?>
</ul>

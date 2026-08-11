<?php
  $qs = static function (array $params): string {
      $parts = [];
      foreach ($params as $k => $v) {
          if ($v === null || $v === '') {
              continue;
          }
          $parts[$k] = $v;
      }
      return $parts ? ('?' . http_build_query($parts)) : '';
  };
?>
<div class="flex items-end justify-between gap-3">
  <div>
    <h1 class="font-display text-3xl">Sub Finder</h1>
    <p class="mt-1 text-sm text-court-500">Find players free at your tipoff<?= $showRankings ? ' with a matching ranking' : '' ?></p>
  </div>
  <?php if ($step !== 'team'): ?>
    <a href="<?= e(url('/subs')) ?>" class="text-sm font-medium text-orange-ball">Start over</a>
  <?php endif; ?>
</div>

<ol class="mt-4 flex flex-wrap gap-2 text-xs font-medium uppercase tracking-wide text-court-400">
  <li class="<?= $step === 'team' ? 'text-orange-ball' : 'text-court-700' ?>">1. Team</li>
  <li class="<?= $step === 'player' ? 'text-orange-ball' : ($player ? 'text-court-700' : '') ?>">2. Player out</li>
  <li class="<?= $step === 'game' ? 'text-orange-ball' : ($game ? 'text-court-700' : '') ?>">3. Game</li>
  <li class="<?= $step === 'results' ? 'text-orange-ball' : '' ?>">4. Subs</li>
</ol>

<?php if ($team || $player || $game): ?>
  <div class="mt-4 rounded-xl border border-court-100 bg-white px-4 py-3 text-sm text-court-600">
    <?php if ($team): ?>
      <p><span class="text-court-400">Team</span> · <?= e(team_label($team)) ?></p>
    <?php endif; ?>
    <?php if ($player): ?>
      <p class="mt-1">
        <span class="text-court-400">Out</span> · <?= e($player['display_name']) ?>
        <?php if ($showRankings && $player['current_ranking'] !== ''): ?>
          <span class="text-court-400">(<?= e($player['current_ranking']) ?>)</span>
        <?php endif; ?>
      </p>
    <?php endif; ?>
    <?php if ($game): ?>
      <p class="mt-1">
        <span class="text-court-400">Game</span> ·
        <?= e($game['away_name']) ?> @ <?= e($game['home_name']) ?>
        · <?= e(format_tipoff($game['tipoff'])) ?>
      </p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($step === 'team'): ?>
  <section class="mt-6">
    <h2 class="font-display text-xl">Which team needs a sub?</h2>
    <ul class="mt-4 space-y-2">
      <?php foreach ($teams as $t): ?>
        <li>
          <a href="<?= e(url('/subs' . $qs(['team' => $t['id']]))) ?>"
             class="flex items-center justify-between rounded-xl border border-court-100 bg-white px-4 py-4">
            <span class="font-semibold"><?= e(team_label($t)) ?></span>
            <span class="text-court-300">›</span>
          </a>
        </li>
      <?php endforeach; ?>
      <?php if (!$teams): ?>
        <li class="rounded-xl border border-dashed border-court-200 px-4 py-8 text-center text-court-500">No teams yet.</li>
      <?php endif; ?>
    </ul>
  </section>
<?php endif; ?>

<?php if ($step === 'player'): ?>
  <section class="mt-6">
    <h2 class="font-display text-xl">Who is out?</h2>
    <ul class="mt-4 space-y-2">
      <?php foreach ($players as $p): ?>
        <li>
          <a href="<?= e(url('/subs' . $qs(['team' => $team['id'], 'player' => $p['id']]))) ?>"
             class="flex items-center justify-between rounded-xl border border-court-100 bg-white px-4 py-4">
            <span class="font-semibold"><?= e($p['display_name']) ?></span>
            <?php if ($showRankings && $p['current_ranking'] !== ''): ?>
              <span class="text-sm text-court-500"><?= e($p['current_ranking']) ?></span>
            <?php else: ?>
              <span class="text-court-300">›</span>
            <?php endif; ?>
          </a>
        </li>
      <?php endforeach; ?>
      <?php if (!$players): ?>
        <li class="rounded-xl border border-dashed border-court-200 px-4 py-8 text-center text-court-500">No players on this roster.</li>
      <?php endif; ?>
    </ul>
    <p class="mt-4">
      <a href="<?= e(url('/subs')) ?>" class="text-sm text-court-500 hover:text-court-900">← Change team</a>
    </p>
  </section>
<?php endif; ?>

<?php if ($step === 'game'): ?>
  <section class="mt-6">
    <h2 class="font-display text-xl">Which game?</h2>
    <ul class="mt-4 space-y-2">
      <?php foreach ($games as $g): ?>
        <li>
          <a href="<?= e(url('/subs' . $qs(['team' => $team['id'], 'player' => $player['id'], 'game' => $g['id']]))) ?>"
             class="block rounded-xl border border-court-100 bg-white px-4 py-3">
            <p class="text-xs font-medium uppercase tracking-wide text-court-400"><?= e(format_tipoff($g['tipoff'])) ?></p>
            <p class="mt-1 font-semibold">
              <?= e($g['away_name']) ?>
              <span class="font-normal text-court-400">@</span>
              <?= e($g['home_name']) ?>
            </p>
            <?php if ($g['status'] === 'final'): ?>
              <p class="mt-1 text-xs uppercase text-court-400">Final</p>
            <?php endif; ?>
          </a>
        </li>
      <?php endforeach; ?>
      <?php if (!$games): ?>
        <li class="rounded-xl border border-dashed border-court-200 px-4 py-8 text-center text-court-500">No games for this team.</li>
      <?php endif; ?>
    </ul>
    <p class="mt-4">
      <a href="<?= e(url('/subs' . $qs(['team' => $team['id']]))) ?>" class="text-sm text-court-500 hover:text-court-900">← Change player</a>
    </p>
  </section>
<?php endif; ?>

<?php if ($step === 'results'): ?>
  <section class="mt-6">
    <h2 class="font-display text-xl">Suggested subs</h2>
    <p class="mt-1 text-sm text-court-500">
      Available at this tipoff<?= $showRankings ? ', ranked at or below the missing player' : '' ?>.
    </p>
    <ul class="mt-4 space-y-2">
      <?php foreach ($suggestions as $i => $sub): ?>
        <li class="rounded-xl border border-court-100 bg-white px-4 py-3">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="font-semibold">
                <span class="mr-2 text-court-400"><?= $i + 1 ?>.</span>
                <?= e($sub['display_name']) ?>
              </p>
              <p class="mt-1 text-sm text-court-500">
                <a href="<?= e(url('/teams/' . $sub['team_id'])) ?>" class="hover:text-court-900">
                  <?= e(team_label(['display_name' => $sub['team_display_name'], 'team_number' => $sub['team_number']])) ?>
                </a>
              </p>
            </div>
            <?php if ($showRankings && $sub['current_ranking'] !== ''): ?>
              <p class="text-sm tabular-nums text-court-600"><?= e($sub['current_ranking']) ?></p>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
      <?php if (!$suggestions): ?>
        <li class="rounded-xl border border-dashed border-court-200 px-4 py-8 text-center text-court-500">
          No matching players available for this tipoff.
        </li>
      <?php endif; ?>
    </ul>
    <p class="mt-4">
      <a href="<?= e(url('/subs' . $qs(['team' => $team['id'], 'player' => $player['id']]))) ?>" class="text-sm text-court-500 hover:text-court-900">← Change game</a>
    </p>
  </section>
<?php endif; ?>

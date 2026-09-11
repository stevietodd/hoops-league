<h1 class="font-display text-3xl">Playoffs</h1>
<p class="mt-1 text-sm text-court-500">
  <?php if ($season): ?>
    <a href="<?= e(url('/seasons')) ?>" class="hover:text-court-900"><?= e($season['name']) ?></a>
  <?php else: ?>
    No active season
  <?php endif; ?>
</p>

<?php if (!$tournament): ?>
  <p class="mt-8 rounded-xl border border-dashed border-court-200 px-4 py-8 text-center text-court-500">
    Playoffs have not started yet.
  </p>
  <?php if ($navIsCommissioner): ?>
    <p class="mt-4 text-center">
      <a href="<?= e(url('/manage/playoffs/new')) ?>" class="text-sm font-medium text-orange-ball">Start playoffs →</a>
    </p>
  <?php endif; ?>
<?php else: ?>
  <?php if ($navIsCommissioner): ?>
    <p class="mt-4">
      <a href="<?= e(url('/manage/playoffs')) ?>" class="text-sm font-medium text-orange-ball">Manage seeds &amp; tipoffs →</a>
    </p>
  <?php endif; ?>

  <p class="mt-2 text-sm text-court-500">
    <?= (int) $tournament['bracket_size'] ?>-team single elimination
    · <?= e(ucfirst(str_replace('_', ' ', $tournament['status']))) ?>
  </p>

  <?php if ($champion): ?>
    <div class="mt-6 rounded-xl border border-orange-ball/30 bg-orange-ball/10 px-4 py-4">
      <p class="text-xs font-medium uppercase tracking-wide text-orange-ball">Champion</p>
      <p class="mt-1 font-display text-2xl"><?= e(team_label($champion)) ?></p>
    </div>
  <?php endif; ?>

  <?php foreach ($rounds as $round): ?>
    <section class="mt-8">
      <h2 class="font-display text-xl"><?= e($round['label']) ?></h2>
      <ul class="mt-3 space-y-3">
        <?php foreach ($round['matchups'] as $m): ?>
          <?php
            $a = $m['slot_a'];
            $b = $m['slot_b'];
            $game = $m['game'];
            $nameA = !empty($a['team_id'])
              ? team_label(['display_name' => $a['team_display_name'], 'team_number' => $a['team_number']])
              : 'TBD';
            $nameB = !empty($b['team_id'])
              ? team_label(['display_name' => $b['team_display_name'], 'team_number' => $b['team_number']])
              : 'TBD';
          ?>
          <li>
            <?php if ($game): ?>
              <a href="<?= e(url('/games/' . $game['id'])) ?>" class="block rounded-xl border border-court-100 bg-white px-4 py-3">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-court-400">
                      Playoff · <?= e(format_tipoff($game['tipoff'])) ?>
                    </p>
                    <p class="mt-1 font-semibold">
                      <?= e($game['away_name']) ?>
                      <span class="font-normal text-court-400">@</span>
                      <?= e($game['home_name']) ?>
                    </p>
                    <?php if ($a['seed'] || $b['seed']): ?>
                      <p class="mt-1 text-xs text-court-500">
                        <?php if ($a['seed']): ?>#<?= (int) $a['seed'] ?><?php endif; ?>
                        <?php if ($a['seed'] && $b['seed']): ?> vs <?php endif; ?>
                        <?php if ($b['seed']): ?>#<?= (int) $b['seed'] ?><?php endif; ?>
                      </p>
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
            <?php else: ?>
              <div class="rounded-xl border border-dashed border-court-200 bg-white px-4 py-3">
                <p class="font-semibold text-court-700">
                  <?= e($nameA) ?>
                  <span class="font-normal text-court-400">vs</span>
                  <?= e($nameB) ?>
                </p>
                <p class="mt-1 text-xs text-court-500">Waiting on prior results</p>
              </div>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

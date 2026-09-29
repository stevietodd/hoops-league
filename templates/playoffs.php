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
    <?php require __DIR__ . '/_champion_banner.php'; ?>
  <?php endif; ?>

  <?php foreach ($rounds as $round): ?>
    <section class="mt-8">
      <h2 class="font-display text-xl"><?= e($round['label']) ?></h2>
      <ul class="mt-3 space-y-3">
        <?php foreach ($round['matchups'] as $m): ?>
          <?php
            $game = $m['game'];
            $letter = $m['letter'] ?? '';
            $tipoff = $m['tipoff'] ?? null;
            $labelA = $m['label_a'] ?? 'TBD';
            $labelB = $m['label_b'] ?? 'TBD';
            $seedA = $m['seed_a'] ?? null;
            $seedB = $m['seed_b'] ?? null;
          ?>
          <li>
            <?php if ($game): ?>
              <a href="<?= e(url('/games/' . $game['id'])) ?>" class="block rounded-xl border border-court-100 bg-white px-4 py-3">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-court-400">
                      Game <?= e($letter) ?>
                      <?php if ($tipoff): ?>
                        · <?= e(format_tipoff($tipoff)) ?>
                      <?php endif; ?>
                    </p>
                    <p class="mt-1 font-semibold">
                      <?= e($labelA) ?>
                      <span class="font-normal text-court-400">vs</span>
                      <?= e($labelB) ?>
                    </p>
                    <?php if ($seedA !== null || $seedB !== null): ?>
                      <p class="mt-1 text-xs text-court-500">
                        <?php if ($seedA !== null): ?>#<?= (int) $seedA ?><?php endif; ?>
                        <?php if ($seedA !== null && $seedB !== null): ?> vs <?php endif; ?>
                        <?php if ($seedB !== null): ?>#<?= (int) $seedB ?><?php endif; ?>
                      </p>
                    <?php endif; ?>
                  </div>
                  <div class="text-right">
                    <?php if ($game['status'] === 'final' && ($game['left_score'] ?? $game['home_score']) !== null): ?>
                      <p class="font-display text-lg leading-none"><?= (int) ($game['left_score'] ?? $game['away_score']) ?>–<?= (int) ($game['right_score'] ?? $game['home_score']) ?></p>
                      <p class="mt-1 text-xs uppercase text-court-400">Final</p>
                    <?php else: ?>
                      <span class="rounded-full bg-court-100 px-2 py-1 text-xs text-court-600">Scheduled</span>
                    <?php endif; ?>
                  </div>
                </div>
              </a>
            <?php else: ?>
              <div class="rounded-xl border border-dashed border-court-200 bg-white px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-court-400">
                  Game <?= e($letter) ?>
                  <?php if ($tipoff): ?>
                    · <?= e(format_tipoff($tipoff)) ?>
                  <?php endif; ?>
                </p>
                <p class="mt-1 font-semibold text-court-700">
                  <?= e($labelA) ?>
                  <span class="font-normal text-court-400">vs</span>
                  <?= e($labelB) ?>
                </p>
                <?php if (!$tipoff): ?>
                  <p class="mt-1 text-xs text-court-500">Waiting on prior results</p>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

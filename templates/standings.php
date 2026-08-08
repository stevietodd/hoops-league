<?php
  $played = static function (array $row): int {
      return $row['wins'] + $row['losses'];
  };
?>
<h1 class="font-display text-3xl">Standings</h1>
<p class="mt-1 text-sm text-court-500"><?= $season ? e($season['name']) : 'No active season' ?></p>

<?php if ($standings): ?>
  <div class="mt-6 overflow-hidden rounded-xl border border-court-100 bg-white">
    <table class="w-full text-left text-sm">
      <thead class="bg-court-100/60 text-xs uppercase tracking-wide text-court-500">
        <tr>
          <th class="px-3 py-2.5 font-medium">#</th>
          <th class="px-3 py-2.5 font-medium">Team</th>
          <th class="px-3 py-2.5 text-right font-medium">W</th>
          <th class="px-3 py-2.5 text-right font-medium">L</th>
          <th class="px-3 py-2.5 text-right font-medium">PCT</th>
          <th class="hidden px-3 py-2.5 text-right font-medium sm:table-cell">PF</th>
          <th class="hidden px-3 py-2.5 text-right font-medium sm:table-cell">PA</th>
          <th class="px-3 py-2.5 text-right font-medium">Diff</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($standings as $i => $row): ?>
          <?php
            $gp = $played($row);
            $pct = $gp ? $row['wins'] / $gp : null;
            $diff = $row['points_for'] - $row['points_against'];
          ?>
          <tr class="border-t border-court-100">
            <td class="px-3 py-3 text-court-500"><?= $i + 1 ?></td>
            <td class="px-3 py-3 font-semibold">
              <a href="<?= e(url('/teams/' . $row['team']['id'])) ?>"><?= e($row['team']['name']) ?></a>
            </td>
            <td class="px-3 py-3 text-right"><?= (int) $row['wins'] ?></td>
            <td class="px-3 py-3 text-right"><?= (int) $row['losses'] ?></td>
            <td class="px-3 py-3 text-right tabular-nums"><?= $pct === null ? '—' : number_format($pct, 3) ?></td>
            <td class="hidden px-3 py-3 text-right sm:table-cell"><?= (int) $row['points_for'] ?></td>
            <td class="hidden px-3 py-3 text-right sm:table-cell"><?= (int) $row['points_against'] ?></td>
            <td class="px-3 py-3 text-right tabular-nums <?= $diff > 0 ? 'text-court-700' : ($diff < 0 ? 'text-red-600' : '') ?>">
              <?= $diff > 0 ? '+' : '' ?><?= $diff ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php else: ?>
  <p class="mt-8 text-court-500">No standings to show yet.</p>
<?php endif; ?>

<p class="text-sm text-court-500">
  <a href="<?= e(url('/manage')) ?>" class="hover:text-court-900">← Manage</a>
</p>
<h1 class="mt-3 font-display text-3xl">Users</h1>
<p class="mt-1 text-sm text-court-500">Promote or demote commissioners. Admins sit above commissioners.</p>

<ul class="mt-6 divide-y divide-court-100 overflow-hidden rounded-xl border border-court-100 bg-white">
  <?php foreach ($users as $u): ?>
    <li class="flex items-center justify-between gap-3 px-4 py-3">
      <div class="min-w-0">
        <p class="truncate font-medium"><?= e(Auth::displayName($u)) ?></p>
        <p class="truncate text-xs text-court-400"><?= e($u['email']) ?></p>
        <p class="mt-1 flex flex-wrap gap-1">
          <?php if (!empty($u['is_admin'])): ?>
            <span class="rounded-full bg-court-700 px-2 py-0.5 text-xs font-semibold text-white">Admin</span>
          <?php endif; ?>
          <?php if (!empty($u['is_commissioner']) || !empty($u['is_admin'])): ?>
            <span class="rounded-full bg-orange-ball/10 px-2 py-0.5 text-xs font-semibold text-orange-ball">Commissioner</span>
          <?php endif; ?>
        </p>
      </div>
      <?php if (!empty($u['is_admin'])): ?>
        <span class="shrink-0 text-xs text-court-400">Protected</span>
      <?php else: ?>
        <form method="post" action="<?= e(url('/manage/users/' . $u['id'] . '/toggle-commissioner')) ?>">
          <?= csrf_field() ?>
          <button type="submit"
                  class="shrink-0 rounded-lg border border-court-200 px-3 py-2 text-sm font-medium <?= !empty($u['is_commissioner']) ? 'text-red-700' : 'text-court-700' ?>">
            <?= !empty($u['is_commissioner']) ? 'Remove commissioner' : 'Make commissioner' ?>
          </button>
        </form>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
</ul>

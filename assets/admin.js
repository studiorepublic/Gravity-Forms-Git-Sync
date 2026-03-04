/**
 * Gravity Forms Git Sync - Admin JavaScript
 */
(function ($) {
	'use strict';

	$(function () {
		// Filter by status
		$('#gf-git-sync-filter').on('change', function () {
			var val = $(this).val();
			$('.gf-git-sync-table tbody tr').each(function () {
				var status = $(this).data('status');
				var show = val === 'all' || status === val;
				$(this).toggle(show);
			});
		});

		// Bulk export
		$('#gf-git-sync-bulk-export').on('click', function () {
			var $btn = $(this);
			$btn.prop('disabled', true);
			$.post(
				gfGitSync.ajaxUrl,
				{
					action: 'gf_git_sync_bulk_export',
					nonce: gfGitSync.nonce
				},
				function (res) {
					if (res.success) {
						alert('Exported ' + (res.data.count || 0) + ' form(s).');
						location.reload();
					} else {
						alert(res.data?.message || 'Export failed.');
					}
				}
			).always(function () {
				$btn.prop('disabled', false);
			});
		});

		// Bulk import
		$('#gf-git-sync-bulk-import').on('click', function () {
			var $btn = $(this);
			$btn.prop('disabled', true);
			$.post(
				gfGitSync.ajaxUrl,
				{
					action: 'gf_git_sync_bulk_import',
					nonce: gfGitSync.nonce
				},
				function (res) {
					if (res.success) {
						alert('Imported ' + (res.data.count || 0) + ' form(s).');
						location.reload();
					} else {
						alert(res.data?.message || 'Import failed.');
					}
				}
			).always(function () {
				$btn.prop('disabled', false);
			});
		});
	});
})(jQuery);

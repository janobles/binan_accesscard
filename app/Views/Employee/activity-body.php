<?php
/**
 * Employee "My Activity" body: search/filter rows + activity table.
 * Rendered inside components/card by Employee/layout.php (activity page) —
 * vars: listRoute, searchTerm, auditAction, auditActionOptions, perPage,
 * perPageOptions, myAudits, hasSearchFilters, formatAuditMember, auditClearUrl.
 */
?>
<?php /* Search toolbar lives in Employee/layout.php, above this card (Manage Records standard). */ ?>
                        <?= view('components/table_controls', [
                            'searchId' => 'activityLocalSearch',
                            'searchAria' => 'Search shown activity',
                            'searchFormAttrs' => 'data-lookup-search',
                            'searchInputAttrs' => 'data-lookup-search-input',
                            'sizeId' => 'activityPerPage',
                            'sizeAction' => site_url($listRoute),
                            'sizeHiddenHtml' => ($searchTerm !== '' ? '<input type="hidden" name="q" value="' . esc($searchTerm, 'attr') . '">' : '')
                                . ($auditAction !== '' ? '<input type="hidden" name="action" value="' . esc($auditAction, 'attr') . '">' : ''),
                            'perPage' => $perPage,
                            'perPageOptions' => $perPageOptions,
                        ]) ?>

                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead><tr><th>Action</th><th>Member</th><th>Description</th></tr></thead>
                                <tbody>
                                    <?php foreach ($myAudits as $audit): ?>
                                        <tr>
                                            <td><span class="status-pill is-muted"><?= esc((string) ($audit['user_action'] ?? '')) ?></span></td>
                                            <td><?= esc(isset($formatAuditMember) ? $formatAuditMember($audit) : '') ?></td>
                                            <td><?= esc((string) ($audit['description'] ?? '')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if ($myAudits === []): ?>
                                        <tr><td colspan="3" class="text-center text-muted audit-empty-state"><?= $hasSearchFilters ? 'No matching activity found.' : 'No activity yet.' ?></td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

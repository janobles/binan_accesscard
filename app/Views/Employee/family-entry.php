<?= $this->extend('Employee/layout') ?>
<?= $this->section('content') ?>
<div class="panel">
    <div class="section-title mt-0"><span>Add Record</span></div>
    <?= view('Dashboard/familyform', array_merge($familyFormViewData ?? [], ['canCreateFamily' => $canCreateFamily ?? false])) ?>
</div>
<?= $this->endSection() ?>

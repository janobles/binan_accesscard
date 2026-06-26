<?php
// Family/form prepares its own view data (FamilyFormViewData::prepare()), so this
// wrapper just forwards whatever the caller passed — no separate prep step here.
echo view('Family/form', get_defined_vars());

<?php
helper('family_form');
extract(family_form_view_data(get_defined_vars()), EXTR_OVERWRITE);

echo view('Dashboard/familyform/familyform', get_defined_vars());

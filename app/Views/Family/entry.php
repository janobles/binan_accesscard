<?php
helper('family_form');
extract(family_form_view_data(get_defined_vars()), EXTR_OVERWRITE);

echo view('Family/form', get_defined_vars());

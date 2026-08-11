// Two rules, deliberately. The browser JS has no build step and no types, and
// files call each other through window.* globals, so an undefined identifier is
// a runtime ReferenceError on whichever page hits the path. no-undef is the only
// check that can see it. Style rules are out of scope: this repo is unformatted
// on purpose.
import globals from 'globals';

export default [
  {
    files: ['public/assets/js/**/*.js'],
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: 'script',
      globals: {
        ...globals.browser,
        // Vendor globals loaded by <script> tags in the layout, and the
        // cross-file entry points the dashboard scripts publish for each other.
        bootstrap: 'readonly',
        Chart: 'readonly',
        DataTable: 'readonly',
        jQuery: 'readonly',
        FullCalendar: 'readonly',
      },
    },
    rules: {
      'no-undef': 'error',
      // args and caughtErrors are both 'none': the value this rule earns its
      // place for is no-undef over the cross-file window.* wiring, not binding
      // hygiene. Unused function parameters and unused catch bindings are
      // idiomatic here and not the failure mode being guarded against.
      'no-unused-vars': ['error', { args: 'none', caughtErrors: 'none' }],
    },
  },
];

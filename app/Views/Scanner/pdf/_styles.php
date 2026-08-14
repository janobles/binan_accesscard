<?php
/**
 * Stylesheet for the distribution report PDF, included by Scanner/pdf/report.php.
 *
 * A view rather than a .css file because dompdf resolves no external assets and needs
 * the rules inlined into the document it renders. DejaVu Sans is dompdf's built-in
 * font, chosen so the report needs no embedded font file.
 */
?>
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
  h1 { font-size: 18px; margin: 0 0 2px; }
  .sub { color: #666; font-size: 10px; margin: 0 0 12px; }
  .kpis td { padding: 6px 10px; border: 1px solid #ddd; text-align: center; }
  .kpis .n { font-size: 16px; font-weight: bold; }
  table.data { width: 100%; border-collapse: collapse; margin-top: 10px; table-layout: fixed; }
  table.data th, table.data td { border: 1px solid #ddd; padding: 4px 6px; text-align: left; overflow: hidden; word-wrap: break-word; }
  table.data th { background: #f4f4f4; }
  table.data.wide th, table.data.wide td { font-size: 9px; padding: 3px 4px; }
  .bar { background: #4e73df; height: 8px; display: inline-block; vertical-align: middle; }
  h2 { font-size: 13px; margin: 14px 0 4px; }
  .note { color: #666; font-size: 9px; margin: 0 0 4px; }

  /* The heatmap prints on an office laser or inkjet, monochrome more often
     than not, so the served scale is a grey ramp rather than a hue: five
     genuinely distinct steps plus white for a staffed hour that served
     nobody. The family count stays in every served/empty cell so the grid
     still reads if the shading does not survive the print run. "Closed" is
     not another step on that ramp; it gets its own hatch fill and a dashed
     border so a worn toner cartridge cannot turn "closed" into "just a very
     light step 1". */
  table.heat { width: 100%; border-collapse: collapse; margin-top: 6px; table-layout: fixed; font-size: 8px; }
  table.heat th, table.heat td { border: 1px solid #999; padding: 3px 2px; text-align: center; }
  table.heat th { background: #f4f4f4; font-weight: bold; }
  table.heat th.rowhead, table.heat td.rowhead { text-align: left; font-weight: bold; background: #f4f4f4; }
  .heat-0 { background: #ffffff; color: #000; }
  .heat-1 { background: #d9d9d9; color: #000; }
  .heat-2 { background: #b3b3b3; color: #000; }
  .heat-3 { background: #8c8c8c; color: #fff; }
  .heat-4 { background: #595959; color: #fff; }
  .heat-5 { background: #262626; color: #fff; }
  /* dompdf does not render a repeating-linear-gradient background reliably,
     so "closed" leans on three signals a printer cannot lose at once: a
     double border, a background one step off the served ramp's white, and
     the word itself in italics rather than a digit. */
  .heat-closed {
    background: #ececec;
    border-style: double;
    border-width: 3px;
    color: #555;
    font-style: italic;
  }
  ul.legend { list-style: none; padding: 0; margin: 4px 0 0; font-size: 8px; color: #444; }
  ul.legend li { display: inline-block; margin-right: 10px; }
  ul.legend .swatch { display: inline-block; width: 10px; height: 10px; margin-right: 3px; vertical-align: middle; border: 1px solid #999; }
</style>

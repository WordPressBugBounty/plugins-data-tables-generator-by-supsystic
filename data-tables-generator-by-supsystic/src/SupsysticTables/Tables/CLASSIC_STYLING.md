# Classic table styling

Styling is the first settings section, before Main. The palette library opens by default. Search and collection/mood filters support 110 palettes; the availability switch is intentionally hidden. Wheel and touch events stay inside the gallery, including at its edges. The picker and all original `styles[...]` controls live together in collapsible groups.

## Compatibility

- `styles[useCustomStyles]`, `styles[customCss]`, all original field names, and table-level CSS remain supported. The controls have moved; their values have not migrated.
- Extended styling is opt-in through `classicDesign[enabled]=on`. Tables without that setting receive no new design class or CSS tokens.
- A palette fills the original header, cell, border and search fields, then the new control and interaction fields. It does not change data, sorting, pagination, source, responsive mode or other feature switches.
- Undo preset restores the appearance immediately before the most recent palette application, including the original preview CSS. It is available during the current editing session.
- User cell colors remain inline and conditional formatting retains its own priority. Sorted and hovered cell backgrounds deliberately exclude cells with explicit background styles.
- WooCommerce and pricing tables are excluded.
- CSS sorting triangles are available on all Classic tables and inherit the header color. Extended styling adds separate normal and active arrow colors. The original sorting-on-hover option is retained.

## Adding palettes and fields

`ClassicDesign.php` defines the field schema, validation, and five FREE palettes. `ClassicDesignLibrary.php` is the shared public preview catalog of 105 PRO palettes: five Essentials and 100 designs in ten collections. `getClassicDesignPaletteLibrary()` exposes all 110 cards in both editions; `getClassicDesignPresets()` returns the edition's applicable palettes. FREE displays full PRO thumbnails and leaves the current appearance untouched when a locked card is clicked. The PRO module uses the shared catalog and retains its previous definitions as a fallback for older FREE versions. Filled button and active-page labels default to white; overly bright fills are darkened within the same hue to retain text contrast. Manual color fields remain editable. A palette must supply every field; `preset()` starts from the complete schema and derives the coordinated colors.

New settings are stored under `classicDesign`. Server output and the live preview both validate hex colors, font weights and numeric bounds. Only known fields become `--stb-*` properties. Public CSS is scoped to the opt-in classes. Changing an individual field marks the appearance as custom and does not reapply a saved palette on reload.

The frontend helper handles CSS sorting triangles, detached date pickers and row context menus. Sorting triangles use their own element so they do not conflict with collapsible row controls. Table clones retain their classes and inline tokens.

The PRO global search inherits the first styled table on a page. Use `[supsystic-tables-global-page-search style_table="123"]` to choose a specific table. The PRO frontend import form inherits the palette of its `id` table.

## Checks

Run `php tests/classic-design.php` from the FREE plugin directory. The standalone checks cover FREE/PRO counts, 100 distinct new palettes in ten equally sized collections, contrast of text states, palette completeness, legacy opt-out, exclusions, dark palette surfaces, and CSS value validation. Browser coverage also needs palette application/undo/save/reload, manual fields, ordinary/global/column search, paging, sorting, editable fields, conditional formatting, fixed columns, server-side mode, and responsive row details.

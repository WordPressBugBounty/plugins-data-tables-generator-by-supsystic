# Pricing table type

Pricing tables use the existing `supsystic_tbl_tables` record and the normal
`[supsystic-tables id=N]` shortcode. The wizard sets
`table_type = pricing_table`. `Tables/Controller::viewAction` delegates the
editor to the Pricing controller, and `Tables/Module::render` delegates
frontend output to the Pricing module. The Pricing model stores GrapesJS HTML,
CSS, theme and section settings in `meta.pricingBuilder`. The meta column is
migrated to LONGTEXT because a GrapesJS design can exceed the old TEXT limit.
Core Data Tables cloning and deletion still operate on the same table ID.

The Free module provides plan cards, feature rows, text, buttons, badges,
images, responsive layouts, theme presets, undo/redo, HTML/CSS editing, clone,
preview and JSON import/export. When a licensed Data Tables PRO is loaded,
the PRO loader replaces the Free Pricing module and controller. The PRO
module adds monthly/yearly pricing toggle controls, per-plan visibility dates and additional theme
presets. Their frontend JavaScript and CSS live in the PRO package. The
builder model validates and sanitizes saved HTML and CSS in
both editions; the active module's capabilities determine which PRO data
is retained.

The editor uses GrapesJS as its main canvas. A separate inspector contains
Blocks, contextual Style, native GrapesJS Layers, Design, Settings, HTML and
CSS tabs. Choosing an element opens Style automatically; links, images, prices
and schedules are edited in Settings. HTML and CSS are independent source
panels. Each code draft is preserved while switching panels and must be
applied to the canvas before Save. Desktop, tablet and mobile previews are
available above the canvas. The GrapesJS background style plugin is loaded
from the ProTech prototype; the saved CSS sanitizer retains safe HTTP(S) and
site-relative image URLs.

GrapesJS itself and its CSS originate from the ProTech prototype. Its
standalone pages, installer, AJAX endpoints and database are not used.

# Gallery Example — OnePagerCMS plugin

Reference plugin for the OnePagerCMS **custom section type API**. It adds an
"Image Gallery" section type: a title plus a list of image URLs, rendered as a
responsive Bootstrap grid on the one-pager.

It demonstrates every part of the section type API:

- `opcms_register_section_type()` with `label`, `build`, `render` and `form_url`
- an own data table (`gallery`), created idempotently in the activate hook
- an admin form via the `opcms_admin_pages` filter (`core/extension.php?page=gallery-form`)
- a redirect-capable POST handler via the `opcms_extension_handlers` filter
  (`misc/extension.php?handler=gallery-example`)
- full cleanup (drop table + `deleteSectionEntriesByType`) in the uninstall hook

See `docs/EXTENSIONS.md` in the OnePagerCMS repository for the API reference.

## Build & install

Create the installable ZIP from this directory:

```bash
zip -r gallery-example.zip plugin.json gallery-example.php README.md
```

Then install it in the OnePagerCMS admin backend (Extensions → Upload), activate
it, and add an "Image Gallery" section on the Sections page.

Requires OnePagerCMS ≥ 1.2.0.

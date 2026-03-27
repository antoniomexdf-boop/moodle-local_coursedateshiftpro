# local_coursedateshiftpro

Pro edition of a Moodle course date rescheduling plugin.

## Purpose

Course Date Shift Pro helps administrators review and apply date changes across a Moodle course with a guided preview workflow before any real update is saved.

## Main Features

- Course selection from Site administration.
- New course start date selection.
- Selective rescheduling by block:
  - course end date
  - activities and resources
  - section date restrictions
  - activity availability restrictions
  - overrides
  - completion expected
- Preview before applying:
  - decision guide with a clear status headline and next recommended action
  - course summary
  - original dates card with start and end
  - new dates card with start and end
  - delta in days
  - ready-to-apply overview with counts for conflicts, review items, suggested dates, and ready items
  - weekly course load overview with total weeks, busy weeks, dated items, a week-by-week load table, and visual load bars
  - integrated review table with week context, status, notes, filters, and sorting
  - two comparison timelines
- Collapsible execution history visible from the start screen.
- Persistent history and rollback.
- Interactive preview through Moodle external services and `core/ajax`.
- Default `Simple review` mode for everyday use, with an optional `Advanced review` mode when deeper technical detail is needed.
- Direct links to activities from the review table.
- Suggested dates for items missing scheduling fields.
- Editable recommended dates directly inside the review table.
- Linked recommended dates inside one activity, so related fields can move together around the main due date.
- Plain-language suggestion reasons inside the review rows, so users can understand why a date was suggested.
- Status labels now follow visible review needs more closely, instead of hidden technical differences alone.
- Blocking validations now highlight the affected rows in red inside the table.
- Rows with blocking issues now switch their status to `Conflict`, so the status filter can isolate them.
- Conflict notes now describe the issue more precisely with concrete dates in the table.
- Suggested dates now respect course bounds and basic field order rules before being proposed.
- Manual recommended-date edits now pass through the same sequence and course-range normalization logic.
- Consecutive rows from the same activity/resource now avoid repeating the full item label visually.
- The review table now shows one expandable row per activity/resource, with field-level selection and date editing inside the detail view.
- Timeline cards now group related fields under the same activity/resource to reduce repetition.
- Filters and sorting now work on grouped activity/resource blocks in the expandable review table.
- Applying changes now uses the same effective recalculated dates already shown as valid in the preview.
- Blocking sequence validation now checks the effective preview dates after manual fixes, not only the original shifted pair.
- The expandable review table now uses an icon toggle and a shorter Moodle date-time format inside the table.
- User-facing messages were rewritten with a clearer, less technical tone for teachers and administrators.
- The `New date` column in the review table now reflects the effective preview value after manual changes.
- Manually edited dates in the preview now keep the exact selected day instead of being moved automatically to the next business day.
- Manual date rules are now documented, and assignment date labels follow Moodle wording more closely.
- The main review table now uses a cleaner stylesheet-based presentation designed to feel more integrated with Moodle.
- The History section now uses icon-based actions and a cleaner Moodle-style presentation.
- The preview now hides timelines, technical filters, and extra date columns by default until the user opens `Advanced review`.
- The preview now includes a weekly load overview so users can quickly understand how many weeks the course spans and how many dated items fall in each week.
- The preview now includes a readiness overview and weekly drill-down details to support faster, safer decisions before applying.
- The preview now starts with a decision guide so users can see the overall state and the next recommended action at a glance.
- The main review table now starts with a course-relative `Week` column that updates when preview dates change.
- The main review flow now defaults to chronological review order based on course week and effective preview date.
- Weekly load drill-down now lists every scheduled activity/resource in that week and orders them by their first effective date in the preview.
- The weekly load overview now follows the same displayed preview dates used in the main review table, so both views stay aligned more closely.
- The weekly load block now separates unique activities/resources, total scheduled dates, and repeated week appearances so the summary is easier to understand.
- Weekly drill-down items now show lightweight start/end markers so users can see whether that week represents the beginning, the end, or the full span of that activity schedule.
- Course-level items now keep a direct link in the main table and inside the weekly load drill-down, so navigation stays consistent even for course end dates.
- The lower History area now works as a dashboard with summary cards, clearer execution impact, and expandable detail for each saved run.
- Stored executions can now be exported to PDF with course dates and line-by-line before/after detail.
- PDF exports now use the Moodle site identity more clearly, with a first-page header that can show the platform logo from `core_admin | logo`, the site name, and a cleaner institutional layout.
- PDF execution detail now includes a dedicated `Flow` column so start/end/same-week markers are easier to read without crowding the item name.
- Manual calendar selection in recommended dates no longer refreshes the preview on the first picker click.
- Course-range validations now follow the effective preview end date, including manual edits made before applying changes.
- Related date suggestions now update together in the preview when one anchor date is changed, especially for assignment-style due date fields.
- Weighted weekly workload detection instead of raw weekly counts.
- Final confirmations always visible before applying changes.
- Suggested balancing changes stay optional until the user explicitly enables them.

## Installation

Plugin component:

`local_coursedateshiftpro`

Expected Moodle installation folder:

`local/coursedateshiftpro`

The Moodle release ZIP must contain this root folder:

`coursedateshiftpro/`

And inside it:

`coursedateshiftpro/version.php`

Base packaging and submission guide:

`docs/publication-guide.md`

Validation and packaging workflow aligned with:

`/Users/antonio/Documents/CODEX/New project/coursecountdown_V14/PLAYBOOK.md`

Release-facing files included:

- `CHANGELOG.md`
- `LICENSE.md`
- `MANUAL_EN.md`

Curated screenshots for this release:

`docs/screenshots/pro-1.9.2/README.md`

## Usage

1. Go to `Site administration > Plugins > Local plugins > Course Date Shift Pro`.
2. Select a course.
3. Load the course.
4. Choose the new course start date.
5. Configure selective rescheduling.
6. Generate the preview.
7. Start in `Simple review` to focus on the most important decisions.
8. Open `Advanced review` only when you need timelines, technical filters, or deeper comparison details.
9. Select, unselect, or edit recommended dates directly in the table as needed.
10. Explicitly decide whether to apply suggested balancing changes.
11. Confirm the final checkboxes.
12. Apply the selected changes.
13. Use `Undo changes` for the most recent execution when needed.
14. Use the history panel to load or roll back previous executions.
15. Use `Export PDF` from the latest execution or any stored execution when you need a formal before/after report.

## Scope

This Pro edition is intended as a commercial-ready functional build. It does not yet include:

- export reporting
- institutional multi-course orchestration
- advanced release automation or CI

## Screenshot Set

The canonical screenshot numbering for this release starts at `01` and is stored in:

`docs/screenshots/pro-1.9.2`

The ordered index is documented in:

`docs/screenshots/pro-1.9.2/README.md`

Recommended README and publication images:

- `01-review-decision-guide-course-load.png`
- `02-course-load-weekly-detail.png`
- `03-simple-review-table.png`
- `05-history-and-restore.png`
- `08-missing-dates-overview.png`
- `10-missing-dates-linked-dates.png`

## Frozen PDF Export Behavior

The current PDF export behavior is considered stable for this release line:

- The first page header prioritizes the Moodle appearance logo configured in `core_admin | logo`.
- If no main logo is available there, the export can fall back to `logocompact`.
- The first page header includes:
  - a top rule
  - a bottom rule
  - the logo at the left when available
  - the site name centered
  - the report title centered under the site name
- The detailed execution table uses a compact visual style for readability in long reports.
- A dedicated `Flow` column is used to show whether one activity starts in that week, ends in that week, or stays within the same week.
- If the Moodle logo is changed later in Appearance settings, the PDF should reflect the new logo after cache refresh.

## Version

- Internal version: `2026032429`
- Release: `1.9.2-pro-pdf-branding-freeze`

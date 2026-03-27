# Manual

Release: `1.9.2-pro-pdf-branding-freeze`

## Overview

Course Date Shift Pro helps administrators review and apply date changes in Moodle courses with a guided preview workflow.

## Workflow

1. Open the plugin from Site administration.
2. Select a course.
3. Load the course.
4. Choose the new course start date.
5. Configure selective rescheduling.
6. Generate the preview.
7. Start with `Simple review`, which opens by default and keeps the most important decisions visible.
8. Switch to `Advanced review` only when you need timelines, technical filters, or more comparison detail.
9. Edit recommended dates directly in the table when needed.
10. Explicitly decide whether to apply suggested balancing changes.
11. Confirm and apply the selected changes.

## Main Areas

- Preview summary cards
- Weekly load overview with course weeks, busy weeks, and dated items per week
- Ready-to-apply overview with conflicts, review items, suggested dates, and ready items
- Decision guide with a headline summary and next recommended action
- Main review table with a course-relative `Week` column for faster orientation
- Comparative timelines
- Review table with notes, filters, and editable recommended dates
- Red row highlighting for blocking validations
- Conflict status filtering for blocking rows
- More precise conflict details with concrete dates in row notes
- Safer suggested dates constrained by course range and field sequence rules
- Manual recommended-date edits now rebalance related field sequences after recalculation
- Reduced visual duplication for repeated rows from the same activity or resource
- Grouped review-table item names and grouped timeline cards to reduce repeated visual entries
- Expandable review rows now present one activity/resource at a time with field-level details inside
- `Simple review` now hides technical columns and filters until the user chooses `Advanced review`
- `Advanced review` reveals timelines, technical filters, and additional comparison columns when deeper analysis is needed
- The weekly load overview helps users understand how many weeks the course spans and how many dated items land in each week before applying changes
- Each week can now show its scheduled items, helping users inspect what is creating load in that week
- Weekly load rows now include visual bars so busy weeks are easier to identify quickly
- The main review table week label updates together with preview recalculations when dates move to a different week
- The main review flow now follows chronological order by default, based on course week and effective preview date
- Weekly load details now show all scheduled activities/resources for that week and list them by their first effective date
- The weekly load overview now follows the same displayed preview dates used in the main review table so both areas stay aligned more closely
- The weekly load summary now separates unique activities/resources, total scheduled dates, and week appearances so users can understand repeated items more easily
- Weekly drill-down items now include small start/end markers so users can quickly see whether a week represents the beginning, the end, or the whole span of one activity schedule
- Course-level items now keep direct links both in the main table and in the weekly load drill-down, so navigation remains consistent
- The History area now appears as a dashboard with summary metrics, clearer execution impact, and expandable execution details
- Stored executions can now be exported to PDF with course-level and line-by-line before/after detail
- PDF exports now use a cleaner first-page header with institutional identity, including the Moodle site name and the configured site logo when available
- PDF execution detail now uses a separate `Flow` column so start/end/same-week markers stay readable
- Course range checks now use the effective preview end date, including manual edits made before applying
- Related recommended dates can now move together inside the same activity when one anchor date is edited
- For assignment-style items, the due date now drives suggested values such as allow submissions from, cut-off date, and grading due date unless the user edits those fields manually
- Suggested dates now include a short explanation when they were created to fill a missing date, balance the workload, or stay aligned with another field
- Review status labels now depend on visible conflicts, notes, and suggestion reasons, so clean rows are easier to trust
- The final apply step now uses the same effective recalculated dates confirmed in the preview
- Blocking sequence checks now follow the effective preview values after manual conflict fixes
- Table dates now use a shorter format and expandable rows use icon toggles
- User-facing messages are written in a clearer, less technical tone for end users
- The `New date` column now updates with the current effective preview value after manual edits
- Manual preview dates now keep the exact selected day unless a real consistency correction is required
- Manual calendar selection now waits for a completed change instead of refreshing on the first picker click
- Automatic suggestions may still move weekend dates to the next business day when the plugin is balancing dates automatically
- Manual date edits keep the exact selected day and time unless a real sequence or course-range correction is required
- Assignment date labels now follow Moodle wording more closely where available
- The main review table now uses a cleaner stylesheet-based layout to feel more integrated with Moodle
- The History section now uses icon actions and a cleaner Moodle-style layout
- Collapsible execution history
- Privacy-aware execution history export and deletion support

## Notes

- Suggestions do not replace user decisions.
- Suggested balancing changes stay optional until the user explicitly enables them.
- Use the history panel to load or roll back previous executions.
- English-only submission assets may be preferred for Moodle directory publication.

## Local QA Walkthrough

Use this order before screenshots or GitHub publication:

1. `DS-SIMPLE`
   Check the full guided review flow with a small course date shift.
2. `DS-HEAVY`
   Check table readability, weekly load, and PDF export with a large course.
3. `DS-MISSING-DATES`
   Check suggested dates, linked assignment dates, and manual edits.
4. `DS-AVAILABILITY`
   Check section and activity date restrictions.
5. `DS-OVERRIDES`
   Check assignment and quiz overrides, then verify History and Restore.

During this walkthrough, confirm:

- The main review table is chronological and understandable.
- Weekly load and the main review table stay aligned after manual edits.
- Linked assignment dates move together when the due date is edited.
- Visible conflicts can be resolved and then applied successfully.
- Each real apply creates a new history record.
- Restore creates a separate history action.
- PDF export shows a useful before/after execution record.
- PDF export should show the site logo in the first-page header when `core_admin | logo` is configured.

## Frozen PDF Export Rules

This release line keeps the following PDF behavior fixed unless a later release explicitly changes it:

- The PDF header reads the site branding from Moodle Appearance settings.
- `core_admin | logo` is the preferred image source for the PDF header.
- `core_admin | logocompact` can be used as a fallback if the main logo is unavailable.
- The first page header is rendered as a fixed layout with:
  - top rule
  - bottom rule
  - left logo area
  - centered site name
  - centered report subtitle
- The execution detail table uses a compact table size for better fit in longer exports.
- The execution detail table uses a dedicated `Flow` column:
  - `Start`
  - `End`
  - `Same week`
- If the site logo changes later, the PDF should follow the new branding after Moodle caches are refreshed.

Detailed QA steps are also available in:

`/Users/antonio/Documents/CODEX/dateshift/docs/moodle-test-courses/qa-checklist.md`

## Screenshot Reference

The final ordered screenshot set for this release is documented in:

`docs/screenshots/pro-1.9.2/README.md`

Recommended manual-oriented screenshots:

- `01-review-decision-guide-course-load.png`
- `02-course-load-weekly-detail.png`
- `03-simple-review-table.png`
- `04-advanced-review-table.png`
- `06-history-expanded-detail.png`
- `09-missing-dates-suggestions-table.png`
- `10-missing-dates-linked-dates.png`
- `12-availability-detected-table.png`

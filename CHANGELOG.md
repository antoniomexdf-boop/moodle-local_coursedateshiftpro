# Changelog

## 1.9.2-pro-pdf-branding-freeze

- Froze the current institutional PDF export layout for this release line.
- Updated the PDF header to use a fixed branded layout with rules, centered platform naming, and Moodle Appearance logo support.
- Moved weekly flow markers into a dedicated `Flow` column in the PDF detail table.
- Reduced PDF detail typography and spacing for better readability in longer exports.
- Documented the stable PDF branding and export behavior in the release-facing documentation.

## 1.9.1-pro-pdf-export

- Added PDF export options for stored executions in History and for the most recent applied change.
- Stored detailed execution lines so each PDF can show where the course and its dated items started and where they ended.
- Refined the PDF into a more institutional format with a branded first-page header driven by Moodle Appearance settings.
- Added a dedicated `Flow` column to the PDF detail table so week-span markers no longer crowd the item label.
- Reduced PDF table typography and spacing for a cleaner long-form report layout.

## 1.9.0-pro-release-candidate

- Marked this build as the first 1.9.0 release candidate after the guided review flow, weekly load overview, linked date suggestions, clearer metrics, and the new History dashboard reached a more mature product stage.

## 1.8.46-pro-history-dashboard

- Redesigned the History section as a full dashboard below the review area instead of a minimal collapsed list.
- Added history summary cards, a clearer impact column, and expandable execution details for each saved run.
- Kept separate actions for loading a saved run for review and restoring an applied execution.

## 1.8.45-pro-weekly-links-sync

- Added a direct course link for the course end date row, so the last item in the main review table no longer appears without a hyperlink.
- Added matching links inside the weekly load drill-down so scheduled items can be opened from both review areas.

## 1.8.44-pro-weekly-start-end-markers

- Added weekly detail markers so users can spot when a listed activity starts, ends, or stays within the same week.
- Improved the weekly drill-down reading flow with lightweight start/end visual cues instead of more text.

## 1.8.43-pro-clearer-weekly-metrics

- Clarified the weekly load language so users can distinguish unique activities/resources, total scheduled dates, and repeated weekly appearances.
- Added a dedicated summary metric for activity appearances by week.
- Added an inline explanation below each weekly drill-down to explain why the same activity may appear in more than one week.

## 1.8.42-pro-weekly-sync-and-date-picker-fix

- Aligned the weekly load overview with the same displayed preview dates used in the main review table, so week activity counts match more closely.
- Ordered each week's activity/resource drill-down by the first effective preview date shown for that activity.
- Stopped refreshing the preview on every intermediate calendar click, so manual date selection is easier to complete.

## 1.8.41-pro-chronological-review-order

- Reordered the main review table so activities now appear in a more chronological flow based on course week and recommended date.
- Stopped trimming the weekly load item list, so each week can now show all scheduled activities/resources in its detail view.
- Aligned the default review sorting with the updated schedule instead of the original course order.

## 1.8.40-pro-simple-review-week-column

- Added a `Week` column at the start of the main review table so users can place each activity inside the course timeline more quickly.
- Kept the week label in sync with preview recalculations, so editing dates updates the visible course week automatically.
- Tightened the simple review table presentation so the default mode feels more complete and intentional.

## 1.8.39-pro-visual-decision-guide

- Added a visual decision guide at the top of the preview with a clear status headline, next recommended action, and key planning metrics.
- Added weekly load bars so the busiest weeks are easier to identify at a glance.
- Improved the weekly load area to feel more like a guided dashboard instead of a purely technical table.

## 1.8.38-pro-clearer-status-logic

- Refined review status logic so `Review suggested change` only appears when there is a visible suggestion reason or review note.
- Prevented clean green rows with no visible notes from staying in review status only because of hidden technical date differences.

## 1.8.37-pro-decision-support-ui

- Added a ready-to-apply summary so teachers can quickly see conflicts, review items, suggested dates, and items already ready to save.
- Expanded the weekly course load block with per-week item details to make busy weeks easier to inspect.
- Added plain-language suggestion reasons inside field rows so users can understand why a date was suggested without reading technical notes first.

## 1.8.36-pro-linked-date-suggestions

- Added linked date suggestions so editing one anchor date can automatically update related recommended dates in the same activity before applying.
- Assignment-style due dates now suggest companion dates such as allow submissions from, cut-off date, and grading due date around the chosen due date.
- Similar linked date behavior now also supports other common paired date patterns such as open/close or available from/until when present.

## 1.8.35-pro-effective-course-end-validation

- Fixed course-range validations so they now compare against the effective preview course end date, including manual edits made before applying.
- Fixed row-level "after course end" notes so they follow the same effective course end shown in the preview.

## 1.8.34-pro-weekly-course-load-overview

- Added a weekly course load overview to help users understand course length, dated activities, busy weeks, and weekly date volume before applying changes.
- Replaced abstract week-level load reading with a clearer week-by-week table numbered from the course start date.
- Added simple load indicators so busy weeks are easier to spot without reading technical warning text first.

## 1.8.33-pro-simple-advanced-review-modes

- Added a default Simple review mode to reduce visual noise for teachers and everyday review work.
- Added an optional Advanced review mode for timelines, technical filters, and deeper comparison detail when needed.
- Hid several technical columns and controls from the default flow while keeping the same grouped review table and field editing workflow.

## 1.8.32-pro-history-icon-actions

- Replaced the History load and rollback text buttons with icon-based actions.
- Styled the History section to feel more integrated with Moodle's default admin interface.

## 1.8.31-pro-moodle-integrated-table-style

- Moved the main review layout styling into a plugin stylesheet for a cleaner, more maintainable Moodle-like UI.
- Reduced dependence on inline styles in the preview, timelines, controls, and expandable review table.
- Updated action buttons and table presentation to feel more integrated with Moodle's default admin interface.

## 1.8.30-pro-manual-rules-and-moodle-labels

- Documented the difference between automatic suggestions and manual date edits in the manual.
- Updated assignment-related field labels to follow Moodle wording more closely where available.

## 1.8.29-pro-manual-date-weekend-fix

- Stopped forcing manually edited preview dates to the next business day.
- Kept business-day normalization only for automatic suggestions, while manual user dates now stay exactly as entered unless a real sequence or range correction is needed.

## 1.8.28-pro-effective-new-date-display

- Updated the review table so the `New date` column now reflects the current effective preview date after manual edits.
- Kept status, notes, and apply behavior aligned with the same effective recalculated values.

## 1.8.27-pro-clearer-user-language

- Rewrote the main user-facing texts with a clearer, less technical tone for teachers and administrators.
- Simplified status labels, notes, validation messages, and action descriptions across the plugin.
- Kept the same workflow while making decisions and outcomes easier to understand.

## 1.8.26-pro-compact-table-ui

- Replaced the expandable table text toggle with an icon-based control closer to Moodle's default UI patterns.
- Shortened table date rendering so the review table uses a more compact Moodle date-time format.
- Kept the expandable grouped review flow while reducing horizontal table width.

## 1.8.25-pro-effective-sequence-validation

- Moved blocking sequence validation to the effective preview values, so resolved manual date edits can clear the apply-time blocker.
- Reduced cases where an old module date order still blocked apply even after the conflict was fixed in the preview.

## 1.8.24-pro-effective-preview-apply

- Made the apply step use the same effective recalculated item set already validated in the preview.
- Reduced cases where a conflict looked solved in the preview but the saved value still used an older field date.

## 1.8.23-pro-expandable-review-table

- Replaced the grouped rowspan review layout with an expandable activity/resource review table.
- Kept filters, sorting, colors, editable recommended dates, and field-level checkboxes inside each expanded detail panel.
- Updated review interactions so the table works by grouped activity/resource blocks instead of loose individual rows.

## 1.8.22-pro-rowspan-alignment-fix

- Fixed grouped item cells so the merged table layout only spans consecutive rows from the same activity or resource.
- Prevented visual misalignment where one grouped item cell could extend into unrelated rows.

## 1.8.21-pro-grouped-preview-layout

- Grouped repeated activity/resource rows visually in the review table by keeping the item name in a single merged cell.
- Grouped timeline cards by activity/resource so related date fields appear together instead of as repeated separate entries.
- Renamed the row status from `Recommended adjustment` to `Suggested review` for a clearer user-facing label.

## 1.8.20-pro-manual-sequence-fix

- Applied the same sequence and course-range normalization to manual recommended-date edits.
- Reduced cases where a manually edited row stayed in conflict because related dates were not rebalanced together.
- Kept repeated activity/resource labels visually compact in consecutive rows.

## 1.8.19-pro-sequence-normalization

- Added a post-process normalization step so suggested dates better respect related field sequences.
- Kept suggested dates constrained within the recalculated course range after normalization.
- Reduced visual duplication when the same activity or resource appears in consecutive rows.

## 1.8.18-pro-safe-autoschedule-bounds

- Restricted suggested dates so they stay within the recalculated course range.
- Prevented suggested dates from breaking basic field sequences such as open/close pairs.
- Reduced recommendation cases where autoschedule could worsen an already inconsistent activity.

## 1.8.17-pro-precise-conflict-details

- Made the review table use the refreshed manual recommended dates after edits.
- Improved conflict notes so they explain the exact issue with concrete dates.
- Kept conflict highlighting and status filtering aligned with the updated row details.

## 1.8.16-pro-conflict-status-filter

- Added a `Conflict` status for rows affected by blocking validations.
- Updated the status filter so blocking rows can be isolated directly from the review table controls.
- Kept red row highlighting aligned with the new conflict status.

## 1.8.15-pro-blocking-row-highlight

- Highlighted rows in red when a blocking validation affects the related date change.
- Added row-level blocking markers for invalid field sequences and invalid recalculated course end dates.
- Kept the blocking issues easier to locate directly in the review table before applying changes.

## 1.8.14-pro-editable-review-table

- Refined the main review table to look closer to Moodle's default administration tables.
- Made the `Recommended date` column editable again during preview.
- Applied manual recommended-date edits even when autoschedule is not enabled, so the edited date can be previewed and applied directly.
- Kept the user as the final decision point for autoschedule suggestions.

## 1.8.13-pro-audit-fixes

- Stopped applying suggested balancing changes automatically in both standard and AJAX preview flows.
- Kept the final GO with the user by leaving suggested balancing unchecked until explicitly selected.
- Completed the Privacy API implementation for execution history export and deletion workflows.
- Removed stale legacy preview helpers from `index.php` to reduce maintenance risk.
- Cleaned package metadata and aligned release-facing documentation for the audit fix release.

## 1.8.11-pro-english-only-package

- Removed the Spanish language pack from the plugin package.
- Converted active release-facing documentation to English.
- Prepared an English-first release package for initial Moodle submission.

## 1.8.10-pro-release-docs

- Added release-facing documentation files for packaging and publication.
- Standardized author attribution according to the release playbook.
- Added `LICENSE.md` and `MANUAL_EN.md`.
- Prepared the plugin for stricter Moodle release validation.

## 1.8.9-pro-postapply-clean-screen

- Hid the course configuration form after applying changes.
- Kept the post-apply screen focused on results, preview state and history.

## 1.8.8-pro-always-visible-confirmations

- Made final confirmation checkboxes always visible before applying changes.

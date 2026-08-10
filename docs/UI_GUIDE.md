# JCA UI Guide

## 1. Direction

The interface should feel welcoming, lively, trustworthy, and school-oriented. Use the PSU screenshots only as functional references for portal login, account setup, enrollment forms, and menu organization. Do not reproduce their dated dense layout or tiny typography.

The school logo is the visual source of truth. Preserve the original `JCA Logo.jpg`; the shared navbar/sidebar brand uses the approved web asset at `public/images/logo/jca_logo.png` without overwriting the source.

## 2. Visual principles

1. **Light-first:** white and soft neutral surfaces dominate; navy anchors headers and navigation.
2. **Lively, not noisy:** gold and warm orange provide highlights; use red mainly for destructive/error states.
3. **Readable:** generous spacing, 16px base text, strong hierarchy, and short line lengths.
4. **Status clarity:** pair color with text and an icon; never rely on color alone.
5. **Role clarity:** the shell visibly identifies the signed-in role and current school year.
6. **Privacy by design:** mask or omit sensitive information when it is not needed for the current task.

## 3. Color tokens

These are accessible UI interpretations of the logo, not sampled brand specifications. Validate final colors with JCA before public launch.

| Token | Value | Use |
|---|---:|---|
| `brand-navy-900` | `#17164F` | Strong headings, footer, selected navigation |
| `brand-navy-700` | `#29267D` | Primary buttons, links, header accents |
| `brand-blue-100` | `#E8ECFF` | Selected/educational information backgrounds |
| `brand-gold-500` | `#F2C318` | Highlights, progress, focus accent; not body text on white |
| `brand-orange-500` | `#E97817` | Warm accent and calls to attention |
| `brand-red-600` | `#C62828` | Errors and destructive actions only |
| `success-700` | `#177245` | Verified/paid/success text |
| `success-100` | `#DCFCE7` | Success badge background |
| `warning-800` | `#854D0E` | Pending/incomplete text |
| `warning-100` | `#FEF3C7` | Pending/incomplete background |
| `surface` | `#FFFFFF` | Main panels |
| `canvas` | `#F7F8FC` | Application background |
| `border` | `#D9DCE7` | Borders/dividers |
| `text` | `#1C2434` | Main text |
| `muted` | `#5E6878` | Secondary text |

Do not use gold text on white for essential content. Verify all text/background pairs against WCAG AA contrast during implementation.

## 4. Typography

- Use the configured system sans-serif stack. The Foundation phase intentionally removed the skeleton's external Bunny font request to avoid an unnecessary third-party request.
- Body: 16px minimum, line-height about 1.5.
- Small metadata: 14px minimum.
- Page title: 28-32px desktop, 24-28px mobile.
- Use weight and spacing before adding more colors.
- Avoid all-caps sentences; reserve uppercase for short labels or references.

## 5. Application shell

### Desktop

- Navy top bar beside the permanent sidebar with the current workspace title, authenticated user context, and sign-out control. Do not render non-functional search, notification, or school-year controls.
- Light full-height sidebar with grouped, icon-supported, role-scoped navigation and a clear selected state. The sidebar scrolls independently when its authorized menu is taller than the viewport.
- Main area uses a readable max width for forms and a wider layout for reports/tables.
- Breadcrumbs only where hierarchy is deeper than one level.

### Mobile

- Compact top bar with logo and menu button.
- Alpine-controlled navigation drawer with focus management, Escape close, backdrop, and scroll lock.
- Primary actions remain reachable without horizontal scrolling.
- Tables become stacked summaries or use deliberate horizontal scrolling with pinned context; do not squeeze text unreadably.

### Role navigation

- **Admin:** Dashboard, Enrollment, Students, Academics, Billing Oversight, Communications, Reports, Users, Settings, Audit.
- **Registrar:** Dashboard, Applications, Students, Requirements, Sections, Academics, Reports.
- **Accounting:** Dashboard, Assessments, Payments, Permits, Financial Reports.
- **Teacher:** Dashboard, My Sections, My Students, Announcements.
- **Guardian:** Home, My Children, Enrollment, Requirements, Financial Summary, Announcements, Profile.
- **Student:** Home, My Enrollment, Requirements, Schedule/Section, Announcements, Profile.

The server decides what navigation is rendered; hiding an item is not authorization.

### Implemented UI foundations

- Reusable local SVG icon, navigation-link, page-header, empty-state, status-badge, field, and button components; no icon or dashboard dependency is required.
- Shared `.jca-input`, label, checkbox, card, and button treatments provide visible focus, consistent sizing, disabled states, and accessible contrast across legacy and modernized forms.
- Internal engineering phase names are not presented as workspace titles. Unapproved production policy remains visible only as a clearly worded operational warning where it affects safe use.
- Empty queues explain what will appear and avoid decorative charts or invented operational data.
- Section Assignment uses queue summaries, anchored workspace sections, status text, deliberate tables, and history-preserving actions.
- Financial Configuration uses anchored configuration areas, labeled controls, policy warnings, and compact summaries while retaining the existing audited server actions.

## 6. Core patterns

### Dashboard

- Begin with a role-specific next-action panel, not decorative statistics.
- Use at most four primary metric cards in one row.
- Show pending work with clear links: requirements to review, applications awaiting assessment, payments due, failed SMS.
- Avoid exposing sensitive names/details in broad dashboard widgets.

### Enrollment progress

Use a vertical stepper on mobile and horizontal stepper where space permits:

1. Application
2. Requirements
3. Assessment when applicable
4. Approval
5. Section
6. Enrollment complete

Payment standing appears as a separate panel so an unpaid balance does not visually imply rejected academic enrollment.

### Status badges

Use the shared `x-ui.status-badge` component for record states in cards, tables, summaries, queues, and public progress views. Pass the canonical status value so the component formats its label and applies the same semantic tone everywhere. Form controls remain normal selects, checkboxes, or radio buttons.

Use stable language and tones across screens:

- Draft/Planned/Pending/Incomplete - amber
- Submitted/Under review - blue
- Assessment required/Waitlisted - amber with an explicit label
- Active/Open/Approved/Verified/Paid/Completed - green
- Rejected/Cancelled/Failed/Revoked/Expired - red
- Inactive/Closed/Archived/Withdrawn - neutral

Badges always include readable text; color is supplementary and never the only status indicator. Context-specific wording may override the generated label without changing the canonical status tone.

Presentation varies by urgency to avoid a wall of identical pills: attention and failure states use soft filled pills with a dot, positive/current states use compact bordered badges with a very light tone-matched fill, and inactive or historical states use flat status text. Metadata such as enrollment category may use the lightly filled bordered variant. The canonical label and tone must remain unchanged when choosing a presentation variant.

### Forms

- One column by default; two columns only for short related fields on wide screens.
- Show required markers and instructions before submission.
- Use real labels, autocomplete attributes, input modes, and examples for mobile/reference formats.
- Preserve values after validation errors.
- Summarize errors at the top and show field-level messages.
- Break long enrollment into resumable steps; show save state and last saved time.
- Confirm before irreversible submission, rejection, waiver, override, reversal, or deletion.

### Enrollment requirement evidence

- Show soft-copy evidence and the continuing hard-copy obligation as two separate states for every configured requirement.
- Tell applicants that a verified soft copy or received hard copy may support approval, but the physical document remains required and may be submitted later.
- Student applicants and authorized Guardians receive private PDF/JPG/PNG upload controls only while the application is actively under review; show the 5 MB limit before upload.
- Registrar/Admin sees one audited preview modal for the current soft copy, plus a separate Verify or Reject/replace decision.
- Registrar/Admin can record receipt, receiving notes, and a correction back to Not Received before review.
- Verification and rejection are available only after receipt; rejection explains that a replacement hard copy is required.
- Only Admin can waive an item, with a required reason. Preserve receipt and review history in audit records.

### Tables and filters

- Search and common filters appear above the table.
- Active filters are visible and removable.
- Server-side paginate large datasets.
- Keep a stable row action menu; do not make the entire row ambiguous.
- Financial tables align amounts right and always show currency.
- Empty states explain why there is no data and what authorized action is next.

### Dialogs and destructive actions

Use Alpine dialogs only for short confirmations. Full workflows use pages. Dialogs require focus trap/return, accessible title, Escape behavior when safe, and an explicit cancel action.

For payment reversals, capacity overrides, waivers, role changes, and document deletion, require a reason in the confirmation flow.

## 7. Page-specific guidance

### Login and invitation setup

- The parent/student `/login` page uses a focused two-column card: a short credential form and audience explanation beside the approved school collage. On small screens the image becomes a compact banner and the form remains the primary content.
- Staff use the separate `/staff/login` page, which clearly names the Admin, Registrar, Accounting, and Teacher audience and links back to the parent/student entry point.
- Both sign-in pages keep real labels, visible keyboard focus, password reveal, recovery help, and a neutral failure message that does not reveal an account's existence or role.
- Use “Application reference,” “Mobile/email,” or school-issued identifier according to the flow; do not expose whether an arbitrary account exists.
- Invitation setup explains expiry and password requirements.
- Do not emulate the PSU page's tiny centered form or send permanent temporary passwords.
- Account recovery is a neutral staff-contact page; it must not ask for an identifier or reveal whether an account exists.
- User management shows reactivation only for disabled accounts. Reactivation and disabling both require visible reason fields; a successful reactivation displays the replacement one-time setup link once.

### Guardian portal

- Child switcher clearly identifies the selected child.
- Each child card shows enrollment status, grade/year, missing requirements, section when assigned, and next action.
- Financial view is a summary/ledger within permitted guardian relationship settings.
- Do not expose another guardian's private contact information.

### Student portal

- A Student-role account must be connected one-to-one to a student profile before private student-profile, section, schedule, or financial records are available.
- Before profile connection, a signed-in Student who owns an application sees only that application's reference, grade/year, status timeline, dual requirement statuses, and private upload controls during active review. Drafts show Resume draft; active non-drafts show View application status; a new-application action returns only after the prior application is rejected, withdrawn, or cancelled.
- Admin connects an existing unlinked Student account from People management only after enrollment approval; the server rejects non-Student accounts, accounts already connected elsewhere, and students without an approved/enrolled record. Grades 1-6 require a visible exception reason and the material identity link is audited.
- People management must show the student profile, approved grade/year, and connected login as separate facts. Account linking requires an explicit identity confirmation, rejects clearly mismatched names, and supports an Admin-only audited disconnect that revokes sessions without deleting the profile or enrollment. Placeholder exception reasons such as `N/A` are rejected.
- People management explains that Grades 1-6 use guardian-assisted enrollment and Grades 7-12 may self-apply from an invited Student login. The manual student-profile form is labeled for existing/legacy records and warns staff not to use it before online enrollment.
- An unused mistaken student profile exposes a reasoned, confirmed Admin deletion control. The server hides or rejects deletion once login, enrollment, guardian, communication, or closed duplicate-review history exists.
- Missing profile linkage remains fail-closed with no student data rendered. Staff roles do not inherit Student portal access.

### Registrar workspace

- Queue-first dashboard: submitted, incomplete, assessment pending, ready for approval, section pending.
- Review layout keeps student summary, checklist, and history visible without opening multiple windows.
- Section assignment uses independently paginated tables for awaiting placement, current assignments, transfer history, and promotion review. Shared server-validated filters narrow student, school year, grade, and promotion status; focused row actions open one reusable placement, transfer, or promotion dialog instead of rendering a full form in every row.
- Raw support documents are separated behind an explicit sensitive-content boundary and audited access.

### Accounting workspace

- Prominent enrollment/student reference and financial standing.
- Assessment, due schedule, posted ledger, and balance are visually distinct.
- Posted entries look immutable. Correction action says “Request/approve reversal,” never “Edit payment.”
- Receipt print view shows transaction facts without unrelated enrollment/support data.
- Phase 6 separates the financial queue, role-scoped summary, full internal ledger, and Admin configuration. Payment evidence has no public preview URL.
- The authenticated navigation exposes the Billing queue/configuration only to permitted staff and a relationship/self-scoped Financial records index to Guardian and Student users.
- Statement/receipt/permit views use standalone A4/A5 print markup with navigation omitted. Receipt reprints retain their reference and show the incremented reprint label; permit output never prints debt or exception details.

### Financial visibility by role

- **Admin:** configuration, complete ledger/audit context, and independent exception decisions; posting still requires an Accounting role.
- **Accounting:** assessment/plan/collection workflows, private evidence, eligible permit issuance, and internal ledger.
- **Registrar:** assessed/paid/balance/plan/authorization summary and already-issued permit only.
- **Teacher:** `Authorized for exam`, `Not yet authorized`, and an issued permit number only for a current teaching assignment.
- **Guardian/Student:** linked/self assessment lines, schedules, posted collections, receipts, balance/credit, and issued permits; never internal notes, evidence, audit actors, or override reasons.

### Teacher workspace

- Default to current assigned sections.
- Student page contains only instructional and contact/escalation information allowed by policy.
- Approved support summary is clearly labeled with effective date and authorizing staff; raw source documents are absent.

## 8. Print and downloadable output

- Use a dedicated print header with logo, school name, report title, school year, generated timestamp, and reference number when required.
- Hide navigation, buttons, filters, and decorative backgrounds.
- Repeat table headers on new pages where browser support permits.
- Avoid splitting a student/receipt summary across pages.
- Phase 8 operational reports use a responsive catalog, visible validated filters, horizontally scrollable wide result tables, explicit empty states, and private export status/actions. Role and record scopes remain server-enforced.
- Phase 8 report print views omit application navigation, filters, and export controls. Until P-006 is approved, they are labeled as development operational layouts and omit invented signatories and control/reference numbers.

## 9. Phase 9 accessibility verification

- Motion and hover transforms respect `prefers-reduced-motion`.
- The public landing, login, and enrollment-start journeys were checked at 1280×720 and 360×800: no horizontal overflow, one page heading, visible labels, 16px mobile fields, keyboard-visible focus, skip navigation, and usable touch targets.
- Authenticated role journeys still require the signed P-008 UAT matrix; automated authorization coverage does not replace school-user acceptance.
- Include “Confidential” and intended audience where appropriate.
- Export only fields authorized for the report and actor.

## 9. Alpine.js boundaries

Good uses:

- navigation drawer;
- disclosure panels;
- tabs with server-rendered content;
- confirmation dialog state;
- local file-selection preview;
- progressive filter controls;
- password visibility toggle.

Do not use Alpine for:

- authorization or sensitive field redaction;
- canonical enrollment/payment state;
- calculating authoritative balances or discount rank;
- storing personal data in browser persistence;
- replacing server validation;
- building a client-side single-page application.

## 10. Accessibility acceptance checklist

- Keyboard-only completion of login, enrollment, review, payment entry, and navigation.
- Visible focus for every interactive control.
- Proper heading order and landmark regions.
- Labels and error associations for all inputs.
- Status changes announced appropriately without excessive live regions.
- Dialog focus is contained and restored.
- Contrast meets WCAG AA for normal and large text.
- Touch targets are comfortably sized.
- Motion is subtle and respects reduced-motion preference.
- Logo has useful alt text where informative and empty alt where decorative.

## 11. Content style

- Use plain, respectful language: “requires additional review,” not labels that stigmatize a child.
- Prefer “Guardian” in system concepts; UI may say “Parent/Guardian.”
- Explain what happens next and who can help.
- Do not expose internal enum codes to users.
- Dates display consistently in an unambiguous local format, for example `10 Jul 2026` or `July 10, 2026`.
- Amounts display as Philippine pesos with two decimal places.

## 12. UI definition of done

- Matches the token system and light/lively direction.
- Works at mobile, tablet, and desktop widths.
- Meets accessibility checklist for the changed journey.
- Shows loading, empty, error, forbidden, and success states.
- Does not leak unauthorized data in HTML, JavaScript, URLs, or print output.
- Uses reusable Blade components where a pattern repeats.
- Uses Alpine only for local progressive behavior.
- Has feature tests for authorization/rendered state and targeted browser/manual checks for interaction and responsive layout.

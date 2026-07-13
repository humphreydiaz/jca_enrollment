# JCA UI Guide

## 1. Direction

The interface should feel welcoming, lively, trustworthy, and school-oriented. Use the PSU screenshots only as functional references for portal login, account setup, enrollment forms, and menu organization. Do not reproduce their dated dense layout or tiny typography.

The school logo is the visual source of truth. The inspected source asset is `JCA Logo.jpg`, an 825 x 787 RGB JPEG. It is not yet a web asset under `public/`. Preserve the original; a future UI phase may create optimized web derivatives and an accessible favicon without overwriting it.

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

- Navy top bar with logo, product name, school-year selector/status, notifications, and profile menu.
- Light sidebar with role-scoped navigation and clear selected state.
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

Use stable language across screens:

- Draft - neutral
- Submitted/Under review - blue
- Incomplete/Payment due - amber
- Assessment required - orange
- Approved/Verified/Paid - green
- Waitlisted - violet or blue with explicit label
- Rejected/Cancelled/Failed - red
- Enrolled - navy/green combination with explicit label

Badges need readable text such as “3 requirements missing,” not an unlabeled colored dot.

### Forms

- One column by default; two columns only for short related fields on wide screens.
- Show required markers and instructions before submission.
- Use real labels, autocomplete attributes, input modes, and examples for mobile/reference formats.
- Preserve values after validation errors.
- Summarize errors at the top and show field-level messages.
- Break long enrollment into resumable steps; show save state and last saved time.
- Confirm before irreversible submission, rejection, waiver, override, reversal, or deletion.

### Document upload

- State allowed formats and maximum size before selection.
- Show filename, type, size, upload/validation state, and replacement rules.
- Preview only safe supported types through an authorized route.
- Never show raw storage paths.
- Explain rejection and allow replacement while retaining review history.

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

- Friendly light page with the logo and school name, one focused card, and clear support contact.
- Use “Application reference,” “Mobile/email,” or school-issued identifier according to the flow; do not expose whether an arbitrary account exists.
- Invitation setup explains expiry and password requirements.
- Do not emulate the PSU page's tiny centered form or send permanent temporary passwords.
- Account recovery is a neutral staff-contact page; it must not ask for an identifier or reveal whether an account exists.

### Guardian portal

- Child switcher clearly identifies the selected child.
- Each child card shows enrollment status, grade/year, missing requirements, section when assigned, and next action.
- Financial view is a summary/ledger within permitted guardian relationship settings.
- Do not expose another guardian's private contact information.

### Registrar workspace

- Queue-first dashboard: submitted, incomplete, assessment pending, ready for approval, section pending.
- Review layout keeps student summary, checklist, and history visible without opening multiple windows.
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

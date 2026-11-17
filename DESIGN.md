# Rifad (رِفاد) --- UI Design System

**Product:** Rifad Healthcare & Family Care Platform\
**Document:** UI/UX Design System\
**Version:** 2.0\
**Status:** Design specification for implementation\
**Languages:** Arabic (primary, RTL) and English (LTR)

------------------------------------------------------------------------

## 1. Purpose and design direction

This document defines how Rifad should look, behave, and organize
information across its public experience and three authenticated
workspaces:

1.  **Individual / Family:** discover care, manage family members, book
    appointments, use membership benefits, pay, and communicate with
    medical centers.
2.  **Medical Center:** manage branches, doctors, services, schedules,
    appointments, and conversations.
3.  **Platform Administration:** manage users, centers, packages,
    benefits, CMS content, permissions, settings, and platform activity.

The design direction is a **calm, contemporary, widget-based healthcare
platform**. It uses modular surfaces and configurable information blocks
without turning every screen into a grid of identical cards.

The system is a UI specification, not a technical architecture document.
It does not change the API, data model, authorization model, or business
rules defined in the PRD.

### 1.1 Design principles

-   **Trust before decoration:** healthcare information must feel clear,
    stable, and credible.
-   **Task-first:** prioritize the next meaningful action, not visual
    density or decorative metrics.
-   **Widget-based, not card-based by default:** use widgets when
    information or an action has a clear boundary and can stand
    independently.
-   **One design language, role-aware experiences:** shared foundations
    and components, with distinct workspace priorities.
-   **Arabic-first, RTL-native:** compose layouts in RTL rather than
    mirroring an LTR design.
-   **Progressive disclosure:** show essential information first; reveal
    advanced controls when needed.
-   **State-aware:** every component reflects the actual business state
    and available actions.
-   **Responsive by design:** mobile is a first-class experience, not a
    compressed desktop dashboard.
-   **Accessible and predictable:** consistent interaction patterns,
    readable typography, keyboard support, and explicit status labels.

### 1.2 What "widget-based" means in Rifad

A widget is a self-contained interface unit with a clear purpose, data
scope, and interaction model. It may present a status, a small
collection of records, a chart, a timeline, or a focused action.

A widget must have: - A clear title or purpose. - A defined data scope
and time range where relevant. - A loading, empty, error, and populated
state. - A clear action or destination when interaction is available. -
A responsive layout that remains usable outside its dashboard grid. -
Permission-aware visibility and actions. - No duplicated or conflicting
source of truth.

Widgets are not an excuse to split every field into a separate panel.
Related information should remain together when separation increases
cognitive load.

**Examples** - "Next appointment" is a useful user widget. - "Family
membership coverage" is a useful widget when it shows covered members
and remaining capacity. - "Today's bookings" is a useful center widget
when it supports operational triage. - A separate widget for every
appointment field is not useful. - A payment total without currency,
period, or status context is not a valid metric widget.

### 1.3 Visual character

Rifad should feel: - Calm, modern, warm, and professional. - Light and
spacious, with restrained use of color. - Structured rather than
ornamental. - Contemporary through typography, spacing, composition, and
interaction---not through excessive gradients, glass effects, or
animation.

Use subtle surface contrast, fine borders, deliberate whitespace, and a
small number of strong accent moments. Avoid excessive shadows, neon
colors, decorative charts, and generic SaaS dashboard patterns that do
not serve healthcare workflows.

------------------------------------------------------------------------

## 2. Experience architecture and workspace shells

Use one shared component system with separate navigation and page
composition for each audience.

### 2.1 Public experience

**Purpose:** help visitors understand Rifad, discover medical centers
and services, compare membership packages, and begin registration or
booking.

**Structure** - Responsive top navigation. - Clear primary action (book
care / explore centers, depending on page context). - Content sections
driven by the CMS section registry. - Footer with essential navigation,
contact, and legal links.

**Public page principles** - Keep the homepage focused on discovery and
conversion. - Use editorial sections and purposeful content layouts; do
not force every CMS section into a dashboard widget. - Directory pages
should prioritize search, filters, location, specialties, doctors, and
availability. - Center and doctor detail pages should make branch
information, services, and available appointment actions easy to find. -
Do not display unverified availability as confirmed. Slot availability
must reflect the latest server-provided state.

### 2.2 Individual and family workspace

**Primary jobs** - See what needs attention. - Book for self or a linked
family member. - Track appointments and their statuses. - Access
membership cards and coverage. - Review subscriptions and payment
history. - Communicate with medical centers. - Manage profile, family,
sessions, and notifications.

**Desktop shell** - Navigation rail/sidebar at the logical start edge. -
Compact top bar for page context, notifications, and account controls. -
Main content with a responsive widget grid. - Optional contextual side
panel for a selected workflow, where it improves the task.

**Mobile shell** - Compact top area with page title and relevant global
actions. - Bottom navigation for the highest-frequency destinations. -
Secondary destinations inside a "More" area or profile navigation. -
Full-width content widgets, with important actions reachable without
horizontal scrolling.

Suggested primary destinations: - Home - Appointments - Membership -
Messages - Profile / More

Do not make the bottom navigation contain every feature. The navigation
model should remain stable and short.

### 2.3 Medical center workspace

**Primary jobs** - Monitor operational activity. - Review and process
incoming appointments. - Manage doctors, branches, services, and
availability. - Communicate with users. - Maintain accurate public
center information.

**Desktop shell** - Collapsible sidebar at the logical start edge. - Top
bar with active center/branch context, notifications, and staff
account. - Main workspace with page title, primary action, filters, and
content. - Optional detail drawer for reviewing a booking or record
without losing list context.

**Mobile shell** - Prioritize appointment triage and essential schedule
tasks. - Use compact navigation and full-screen detail views where
tables cannot remain usable. - Do not shrink desktop tables until their
controls and content become unreadable.

Center context must be visible when staff can operate across branches.
Never imply that a change applies to all branches when it applies only
to the selected branch.

### 2.4 Administration workspace

**Primary jobs** - Monitor platform operations. - Manage users, centers,
packages, benefits, CMS, newsletter, settings, roles, and activity. -
Perform sensitive actions with clear context and confirmation.

**Structure** - Persistent, collapsible navigation. - Top bar with
workspace identity, search where supported, notifications, and
administrator menu. - Main content with page title, breadcrumbs where
useful, filters, and a clearly defined primary action. - Dense
data-management views with strong hierarchy and restrained decoration.

Admin screens may be denser than user-facing screens, but must remain
readable and accessible. Sensitive actions must never be hidden inside
ambiguous icon-only menus.

### 2.5 Navigation rules

-   Navigation visibility is derived from the permissions returned by
    `/me`.
-   Frontend visibility is a usability convenience only. Backend
    authorization remains authoritative.
-   Hide unavailable destinations rather than presenting dead-end links.
-   Do not treat a hidden navigation item as proof that the user is
    unauthorized; the server decides.
-   Preserve the current page and filters when opening a detail drawer
    and returning to a list.
-   Use labels with icons. Avoid icon-only primary navigation unless the
    icon is universally recognizable and has an accessible name.

------------------------------------------------------------------------

## 3. Widget system

### 3.1 Widget anatomy

A standard widget may contain:

1.  **Header:** title, optional description or time range, optional
    contextual menu.
2.  **Primary content:** the key information or task.
3.  **Supporting context:** status, comparison, timestamp, or short
    explanation.
4.  **Action area:** one primary action and, if needed, a secondary
    action.
5.  **Feedback state:** loading, empty, error, stale, or updated.

Do not force every widget to use all five parts. Use the minimum
structure needed.

### 3.2 Widget categories

  -----------------------------------------------------------------------
  Category                Purpose                 Examples
  ----------------------- ----------------------- -----------------------
  Status                  Communicate a current   Membership status,
                          state                   center approval state

  Action                  Complete a focused task Add family member,
                                                  create service

  List                    Preview a small set of  Upcoming appointments,
                          records                 recent payments

  Metric                  Summarize a measurable  Bookings today, unread
                          value                   conversations

  Timeline                Show ordered events     Appointment progress,
                                                  activity history

  Chart                   Explain a trend or      Booking volume over
                          distribution            time

  Identity                Present a person or     Doctor, center, family
                          organization            member

  Verification            Confirm an important    Membership card
                          credential or state     verification

  Content                 Present editorial or    Featured centers, FAQs
                          CMS material            
  -----------------------------------------------------------------------

### 3.3 Widget layout rules

-   Use a responsive grid with a clear hierarchy: one or two prominent
    widgets, followed by supporting widgets.
-   Prefer varied spans based on importance and content---not arbitrary
    visual variety.
-   A chart or long list may need a wide span; a compact status widget
    may need a narrow span.
-   Avoid more than two levels of nested containers.
-   Do not put a card inside a card unless the inner surface represents
    a genuinely separate object.
-   Keep widget headers consistent across a workspace.
-   On mobile, stack widgets in priority order. Do not preserve desktop
    column order if it produces a poor task sequence.
-   Do not introduce user-configurable drag-and-drop layouts in the MVP
    unless explicitly approved as product scope. The visual system may
    support future customization, but the initial dashboard should use
    curated layouts.

### 3.4 Widget behavior

-   A widget action must lead to a valid destination or perform a
    clearly scoped operation.
-   Use a contextual menu only for secondary actions.
-   A widget that displays a list should have a sensible preview limit
    and a "View all" destination when a full list exists.
-   A metric must identify its unit, period, and scope.
-   A chart must have readable labels, a legend where needed, and a
    textual summary of the key insight.
-   Do not use charts for tiny datasets that are clearer as text or a
    list.
-   A stale value must not look freshly updated. Show its last-updated
    time when freshness matters.
-   Avoid automatic widget refresh that unexpectedly changes the user's
    current task or selection.

### 3.5 Dashboard composition

**Individual home** 1. Welcome/context area, including the currently
selected person when relevant. 2. Next appointment or a clear
first-booking prompt. 3. Membership status and family coverage. 4.
Recent or upcoming activity. 5. Unread messages and notifications. 6.
Discovery or recommended next action, where useful.

**Center home** 1. Operational summary for the selected branch or center
scope. 2. Appointments requiring attention. 3. Today's schedule. 4.
Unread conversations. 5. Doctor/service/availability shortcuts. 6.
Relevant operational trends, only when the underlying data supports
them.

**Admin home** 1. Platform status and operational attention items. 2.
Centers awaiting review or requiring action. 3. User and membership
overview. 4. Payment/webhook operational indicators where available. 5.
Recent sensitive activity. 6. CMS and platform-management shortcuts.

Dashboard widgets must not invent KPIs or imply monitoring capabilities
that are not present in the PRD or API. If a metric is not currently
supported, omit it rather than showing placeholder data.

------------------------------------------------------------------------

## 4. Design tokens

Define tokens centrally using CSS variables. Components should consume
semantic tokens, not hard-coded color values.

### 4.1 Color system

Use a restrained teal-led healthcare palette with warm neutral surfaces.

  Token                    Intent
  ------------------------ --------------------------------------------
  `--background`           App/page background
  `--foreground`           Primary text
  `--surface`              Main cards, panels, and menus
  `--surface-muted`        Secondary or subdued surface
  `--surface-raised`       Dialogs and floating layers
  `--primary`              Main brand action and selected states
  `--primary-foreground`   Text/icons on primary
  `--secondary`            Secondary action surface
  `--accent`               Limited highlight, not a second primary
  `--muted`                Low-emphasis content surface
  `--muted-foreground`     Secondary text
  `--border`               Standard boundaries
  `--input`                Input border
  `--ring`                 Focus indicator
  `--success`              Successful or active state
  `--warning`              Attention required
  `--destructive`          Errors, destructive actions, cancellations
  `--info`                 Neutral informational state

Suggested starting palette: - Deep teal for primary actions. - Pale teal
for selected and supportive surfaces. - Warm off-white / cool neutral
for the application background. - Dark slate for primary text. - Muted
slate for secondary text. - Semantic green, amber, red, and blue for
state communication.

Do not use the brand color as the color for every status. Status colors
must retain their semantic meaning.

Example semantic token structure:

``` css
:root {
  --background: 210 25% 98%;
  --foreground: 222 25% 14%;
  --surface: 0 0% 100%;
  --surface-muted: 210 20% 96%;
  --surface-raised: 0 0% 100%;

  --primary: 174 62% 30%;
  --primary-foreground: 0 0% 100%;
  --secondary: 174 28% 94%;
  --accent: 38 85% 50%;

  --muted: 210 20% 95%;
  --muted-foreground: 215 14% 42%;
  --border: 214 18% 89%;
  --input: 214 18% 84%;
  --ring: 174 62% 30%;

  --success: 152 55% 34%;
  --warning: 38 88% 43%;
  --destructive: 0 70% 46%;
  --info: 205 72% 42%;

  --radius-sm: 0.5rem;
  --radius-md: 0.75rem;
  --radius-lg: 1rem;
  --radius-xl: 1.25rem;
}
```

The values above are a starting point, not final accessibility approval.
Validate actual foreground/background combinations in both themes.

### 4.2 Dark mode

Dark mode may be introduced after the light theme is stable. When
implemented: - Remap semantic tokens; do not invert colors
mechanically. - Preserve status colors with adequate contrast. - Avoid
pure black backgrounds and excessively bright surfaces. - Check charts,
QR codes, dialogs, disabled states, and form controls. - Respect the
system preference and provide a clear manual override if theme selection
is exposed.

### 4.3 Spacing and sizing

Use a 4px base spacing scale.

  Token          Value Typical use
  ------------ ------- ------------------------------
  `space-1`        4px Tight icon/text gap
  `space-2`        8px Compact internal gap
  `space-3`       12px Dense component spacing
  `space-4`       16px Standard component padding
  `space-5`       20px Comfortable grouping
  `space-6`       24px Widget padding / section gap
  `space-8`       32px Major section separation
  `space-10`      40px Large page separation
  `space-12`      48px Landing page section rhythm

-   Keep control heights consistent within a form.
-   Aim for touch targets of at least 44 × 44px.
-   Use denser spacing in admin tables only when it does not compromise
    touch or readability.
-   Avoid arbitrary one-off spacing values.

### 4.4 Radius, borders, and elevation

-   Inputs and compact controls: 8--10px.
-   Standard widgets: 12--16px.
-   Large feature surfaces: 16--20px.
-   Pills: fully rounded only for compact tags, filters, and status
    chips.
-   Prefer subtle borders to shadows.
-   Use elevation for overlays and floating surfaces, not to make every
    widget appear to float.
-   Avoid excessive blur and glassmorphism, especially behind important
    healthcare data.

### 4.5 Motion

Motion should communicate state, continuity, and hierarchy.

-   Use short transitions for hover, focus, selection, and disclosure.
-   Use restrained entrance transitions for dialogs, drawers, and menus.
-   Avoid animated counters, constant pulsing, decorative parallax, and
    motion that delays a task.
-   Respect `prefers-reduced-motion`.
-   Never use motion as the only indication that a status changed.

------------------------------------------------------------------------

## 5. Typography and content

### 5.1 Font system

Use **IBM Plex Sans Arabic** as the preferred primary family for Arabic
and Latin consistency. Noto Sans Arabic is an acceptable fallback.
Verify glyph coverage and visual balance before finalizing.

Recommended weights: - 400: body text. - 500: labels and supporting
emphasis. - 600: widget titles and buttons. - 700: page titles and
strong emphasis.

### 5.2 Type scale

  Role                             Size   Weight
  -------------------------- ---------- --------
  Display / marketing hero     36--48px      700
  Page title                   28--32px      700
  Section title                20--24px      600
  Widget title                 16--18px      600
  Body                         15--16px      400
  Supporting text              13--14px      400
  Metadata / compact label         12px      500

-   Arabic body line-height: approximately 1.65--1.8.
-   Latin body line-height: approximately 1.5--1.65.
-   Avoid long all-bold Arabic paragraphs.
-   Do not use tiny metadata for essential appointment, payment, or
    membership information.

### 5.3 Content design

Use concise, direct, reassuring language. Avoid medical claims,
guarantees, or ambiguous promises.

-   Use clear action labels: "احجز موعدًا", "إعادة الجدولة", "عرض
    البطاقة".
-   State what happened and what the user can do next.
-   Distinguish "طلب الحجز" from "تأكيد الموعد".
-   Distinguish payment processing from successful payment.
-   Use explicit date, time, branch, and timezone context where
    relevant.
-   Use consistent Arabic terminology across public pages, dashboards,
    notifications, and emails.
-   Avoid relying on icons or color without text.

### 5.4 Numbers, dates, and currency

Choose formatting centrally and apply it consistently: - Support
locale-aware number formatting. - Keep phone numbers, email addresses,
identifiers, QR payloads, and other machine-readable strings in an
LTR/bidi-isolated context. - Display currency with its ISO currency
context or a clear localized currency label. - Display dates and times
in the branch timezone for appointments. - Store/display timezone-aware
values consistently; never infer a branch timezone from the user's
device. - Gregorian dates are the baseline; optional Hijri display must
be clearly distinguished and must not replace the underlying appointment
timestamp.

------------------------------------------------------------------------

## 6. RTL and localization rules

Arabic is the primary design direction. Every shared component must be
tested in RTL and LTR.

-   Use CSS logical properties and Tailwind logical utilities (`ms-*`,
    `me-*`, `ps-*`, `pe-*`, `start-*`, `end-*`, `text-start`).
-   Avoid hard-coded left/right positioning when a logical equivalent
    exists.
-   Set document `dir` and `lang` from the active locale.
-   Use `dir="auto"` or bidi isolation for user-generated mixed-language
    content where appropriate.
-   Keep phone numbers, emails, payment references, IDs, and code LTR
    using `<bdi>` or a scoped `dir="ltr"`.
-   Mirror directional navigation icons when appropriate; do not mirror
    semantic icons such as check, clock, play, or medical symbols.
-   Ensure drawers, dropdowns, tooltips, calendars, date ranges, and
    popovers open from the correct logical side.
-   Do not reverse the order of a workflow if it changes meaning. RTL
    changes spatial direction, not business sequence.
-   Test long Arabic names, mixed Arabic/English text, and narrow mobile
    widths.
-   Provide translated validation and status messages, not just
    translated labels.

------------------------------------------------------------------------

## 7. Responsive layout

### 7.1 Breakpoints

Use the project's Tailwind breakpoints as the baseline:

  Breakpoint      Width Layout behavior
  ------------ -------- -----------------------------------------
  `sm`            640px Wider mobile / compact layout
  `md`            768px Tablet composition
  `lg`           1024px Desktop shell begins where appropriate
  `xl`           1280px Full workspace composition
  `2xl`          1536px Wider content with controlled max-width

Treat breakpoints as layout decisions, not device labels.

### 7.2 Grid and content width

-   Public marketing content: controlled max-width, approximately
    1200--1280px.
-   Dashboard content: fluid width with a readable maximum for
    text-heavy pages.
-   Widget grids: 12-column desktop grid, simplified to fewer columns at
    tablet widths, single-column on narrow mobile.
-   Tables: use responsive column priorities, horizontal scrolling only
    when unavoidable, and mobile record views for complex operational
    tables.
-   Keep the main content aligned consistently across pages.

### 7.3 Responsive behavior

Do not merely scale down desktop layouts: - Convert sidebars into
drawers or compact navigation. - Convert multi-column forms into a
single-column sequence. - Replace dense tables with stacked record
summaries when appropriate. - Keep primary actions visible and
reachable. - Use bottom sheets for short mobile selections where they
are easier than desktop-style popovers. - Avoid sticky elements that
consume too much mobile viewport height. - Ensure booking summaries
remain available without obscuring the slot picker or confirmation
action.

------------------------------------------------------------------------

## 8. Component foundations

### 8.1 Component strategy

Use a shared, accessible primitive layer and compose Rifad-specific
patterns on top.

Recommended implementation foundation: - **Primitives:** shadcn/ui with
Radix UI. - **Styling:** Tailwind CSS with semantic CSS variables. -
**Icons:** Lucide. - **Forms:** React Hook Form + Zod. - **Tables:**
TanStack Table with Rifad-specific responsive presentation. -
**Server/client data UI:** Next.js server components for public content;
TanStack Query for interactive dashboard data. - **Charts:** Recharts,
used selectively. - **Dates:** date-fns plus locale/timezone-aware
formatting utilities. - **Notifications/toasts:** Sonner or an
equivalent accessible system.

The chosen libraries do not define the product behavior. Business rules
and visual states must be implemented in Rifad components.

### 8.2 Primitive components

Build and document: - Button: primary, secondary, outline, ghost,
destructive, link. - IconButton with accessible label and tooltip where
useful. - TextInput, Textarea, PasswordInput, SearchInput. - Select,
Combobox, MultiSelect where needed. - Checkbox, RadioGroup, Switch. -
FormField, FieldHint, FieldError. - Badge / StatusBadge. - Avatar and
AvatarGroup. - Tabs and segmented control. - Tooltip, Popover,
DropdownMenu. - Dialog, AlertDialog, Sheet / Drawer. - Calendar and
DatePicker. - Skeleton, Spinner, Progress. - Toast / InlineAlert. -
Breadcrumbs, Pagination. - Divider and EmptyState.

### 8.3 Interaction standards

-   Buttons must have clear labels and visible disabled/loading states.
-   Prevent accidental duplicate submission while an operation is
    pending.
-   Preserve form values when a recoverable server error occurs.
-   Confirm destructive actions with specific consequences.
-   Use dialogs for decisions requiring attention; use drawers for
    contextual inspection and editing.
-   Do not nest interactive controls inside other interactive controls.
-   Make focus order follow the visual and task order.
-   Provide visible keyboard focus.
-   Use optimistic updates only when rollback and reconciliation
    behavior are defined.

------------------------------------------------------------------------

## 9. Rifad-specific component system

These composites encode repeated product patterns. Their states and
actions must follow the PRD.

### 9.1 Identity and family

**`PatientSelector`** - Selects "Me" or a linked family member for a
booking or relevant history view. - Shows the selected person's name and
relationship where appropriate. - Must not expose another account's
family members. - Changing the selected person must update the
downstream context, not silently modify an existing appointment. - If a
family member cannot be removed because of future appointments, show the
blocking reason and the required next step.

**`FamilyMemberCard`** - Shows only appropriate profile details. -
Supports view/edit/remove according to owner permissions. - Avoid
exposing National ID in full; if shown at all, it must be masked. -
Clearly distinguishes family profile management from login/account
management.

### 9.2 Discovery

**`CenterCard`** - Center name, logo, location/branch context, selected
specialties or services, and a clear detail/booking action. - Show
active/available status only when supplied by the system. - Suspended
centers must not appear in the public directory.

**`DoctorCard`** - Doctor name, photo, specialty, branch context, and
profile/availability action. - Do not imply a doctor is accepting
bookings unless the availability data supports it.

**`ServiceCard`** - Service name, description, duration, and price when
configured. - Distinguish "price not provided" from a free service. - Do
not imply that appointment payment is supported; the PRD assumes
membership revenue only and leaves appointment payments to centers out
of scope.

**`DirectoryFilterBar`** - Search by city, specialty, doctor, and
service. - Filters must be reflected in the URL where practical, so
results can be revisited and shared. - Show active filter count and
provide a clear reset action. - On mobile, filters open in a sheet and
remain applied until changed or reset.

### 9.3 Booking and scheduling

**`BookingStepper`** - Represents the actual sequence: choose person →
choose center/doctor/service → choose date and slot → review and
submit. - Show progress and allow safe backward navigation. - Preserve
valid selections when navigating backward. - Do not claim that a slot is
reserved until the backend confirms the operation.

**`SlotPicker`** - Shows dates and concrete time slots returned by the
backend. - Distinguishes available, unavailable, selected, and
stale/refresh-needed states. - Never invent or locally generate
availability. - Refresh or revalidate before final submission as
required by the API. - If a slot becomes unavailable, explain the
conflict and offer newly fetched alternatives. - Display times in the
branch timezone.

**`BookingSummary`** - Shows the selected person, center, branch,
doctor, service (if any), date, time, and any applicable price
information. - Keep the summary visible during review without
obstructing the mobile flow. - Do not display an appointment payment
amount as payable unless that capability is added to the product scope.

**`AppointmentCard`** - Shows appointment date/time, center/branch,
doctor, person booked for, and status. - Actions are conditional on
current status, ownership, permissions, and cancellation cutoff. - Use a
consistent status label and explanatory copy.

**`AppointmentTimeline`** - Shows meaningful lifecycle events where data
exists. - Must not fabricate confirmation or completion timestamps. -
Distinguish pending, confirmed, completed, cancelled, and no-show.

**`ScheduleCalendar`** - Center-side calendar for doctor/branch
availability and exceptions. - Clearly distinguish recurring
availability from date-specific exceptions. - Prevent ambiguous edits to
the selected doctor, branch, or date range. - Show timezone context.

### 9.4 Membership and billing

**`MembershipCard`** - A digital credential surface with holder, card
number, validity, status, and QR code. - QR payload is an opaque signed
token; never encode personal data. - QR code must have sufficient
contrast and a quiet zone for scanning. - Clearly show active, expired,
suspended, or cancelled status. - Do not display billing details to a
center verifying a card. - Family coverage must reflect the configured
member limit and actual linked coverage.

**`MembershipCoverage`** - Shows owner and covered family members, plus
capacity where supported. - Distinguishes eligible family profiles from
members actually covered by the package. - Never imply coverage for a
person unless the subscription/card data confirms it.

**`PackageCard`** - Package name, price, currency, billing
interval/duration, member limit, and benefits. - Mark a package as
recommended only when there is a deliberate product rule or editorial
configuration. - Do not use misleading savings claims. - Clearly
distinguish recurring billing from one-time duration. - Explain what
happens after purchase only when the behavior is supported.

**`SubscriptionStatus`** - Shows the subscription's actual state and
current period end. - If cancellation is scheduled for period end,
communicate that access remains until the stated date when supported by
the subscription state. - Do not equate a failed payment with immediate
cancellation unless the backend state says so.

**`PaymentRecord`** - Shows amount, currency, date, status, and
invoice/receipt action when available. - Distinguishes pending,
succeeded, failed, and refunded. - Never collect or display full card
data. Only permitted summary details such as brand and last four digits
may be shown.

**`PaymentProcessingState`** - Clearly communicates that payment is
being processed. - The browser return/success page is not proof that
membership has been issued. - Membership activation must follow verified
backend/webhook state. - Provide a safe refresh/retry path without
causing duplicate purchases.

### 9.5 Communication and notifications

**`ConversationListItem`** - Center identity, last message preview,
timestamp, and unread count. - Do not expose message content to users
who are not conversation participants.

**`ChatThread` / `MessageBubble`** - Align by sender using logical
start/end, not hard-coded left/right. - Show timestamp and delivery/read
state only when the server provides it. - Support text, image, and voice
message types. - Persisted messages remain the source of truth;
real-time events update the interface and reconnect behavior reconciles
missed messages. - If real-time transport is unavailable, show a
recoverable connection state and support the defined fallback behavior.

**`VoiceMessage`** - Maximum duration: 60 seconds. - Show duration,
playback state, and upload/send state. - Do not display a playable
message until server-side validation has completed.

**`ImageAttachment`** - Maximum file size: 5 MB. - Show preview, upload
progress, failure, and retry states. - Do not treat client-side
validation as a replacement for server validation. - Use private media
URLs and respect their expiration.

**`NotificationCenter`** - Unread count, list, timestamp, category, and
mark-read behavior. - Critical security and payment emails cannot be
disabled through notification preferences. - Do not imply that an email
was delivered merely because an in-app notification exists.

### 9.6 Operations and administration

**`DataTable`** - Supports sorting, filtering, pagination, row
selection, and row actions where applicable. - Keep filters and
pagination stable when opening a detail view. - Provide explicit empty,
loading, and error states. - Use bulk actions only when their scope and
consequences are clear. - On mobile, use a record-list layout for tables
that cannot be read comfortably.

**`FilterBar`** - Displays active filters and offers a clear reset. -
Distinguishes search from structured filters. - Reflects applied filters
in the visible result context.

**`StatWidget`** - Displays a value, label, unit, scope, and period. -
Supports loading, unavailable, and error states. - Never uses a
decorative trend arrow without actual comparable historical data.

**`ActivityLogItem`** - Shows actor, action, target, and timestamp. -
Use clear language for sensitive admin operations. - Do not expose IP or
sensitive metadata to roles that are not authorized to view it.

**`ApprovalReviewPanel`** - Shows center information and review
status. - Approval/suspension actions require explicit permission and
confirmation. - Explain the consequence of suspension: the center
disappears from public discovery and cannot receive new bookings;
existing bookings are retained.

**`CmsSectionEditor`** - Supports section type, active state, order,
localized content, and type-specific fields. - Form fields derive from
the selected section schema. - Preview should represent the public
rendering as closely as possible. - Unknown section types must not break
the editor or public page. - Reordering must provide clear position
feedback and a keyboard-accessible alternative.

------------------------------------------------------------------------

## 10. Business-state UI contract

This section is mandatory. UI must reflect backend state; it must never
invent, infer, or prematurely grant a business state.

### 10.1 General state model

Every data-driven component must define the relevant subset of these
states:

  -----------------------------------------------------------------------
  State                               UI treatment
  ----------------------------------- -----------------------------------
  Loading                             Layout-matched skeleton; avoid fake
                                      values

  Empty                               Explain why there is no content and
                                      offer a valid next action

  Error                               Plain-language message and safe
                                      retry where possible

  Partial                             Show available data and identify
                                      what could not load

  Stale                               Identify that displayed data may
                                      need refresh when freshness matters

  Success                             Show confirmed result and next
                                      useful action

  Disabled                            Explain why the action is
                                      unavailable when the reason is
                                      actionable

  Forbidden / unavailable             Do not reveal protected data; show
                                      a safe access message
  -----------------------------------------------------------------------

Use skeletons that resemble the final content. Avoid full-page spinners
for routine data loading.

### 10.2 Appointment lifecycle

  -------------------------------------------------------------------------
  Backend status    UI label          Typical             UI behavior
                                      presentation        
  ----------------- ----------------- ------------------- -----------------
  `pending`         Pending           Attention / neutral Do not describe
                    confirmation                          as confirmed

  `confirmed`       Confirmed         Success             Show allowed
                                                          manage actions

  `completed`       Completed         Neutral / success   No cancellation
                                                          or reschedule
                                                          action

  `cancelled`       Cancelled         Destructive-muted   Retain history;
                                                          no active-slot
                                                          actions

  `no_show`         No-show           Warning / neutral   Retain history;
                                                          no active-slot
                                                          actions
  -------------------------------------------------------------------------

Rules: - Only the server can confirm a booking. - A pending appointment
is not a confirmed appointment. - Cancellation availability follows the
configured cutoff. - Rescheduling is atomic on the backend: the new slot
is reserved before the old slot is released. - If a booking conflict
returns (for example, HTTP 409), preserve the user's context, explain
that the slot is no longer available, and refresh alternatives. - Never
optimistically show a booking as confirmed before the API confirms it.

### 10.3 Membership card lifecycle

  -----------------------------------------------------------------------
  Status                              UI behavior
  ----------------------------------- -----------------------------------
  `active`                            Show valid card and QR verification
                                      affordance

  `expired`                           Show expired state; do not imply
                                      current coverage

  `suspended`                         Show unavailable/blocked state and
                                      appropriate support guidance

  `cancelled`                         Show cancelled state and retain
                                      historical details as permitted
  -----------------------------------------------------------------------

The frontend must not calculate or grant membership validity
independently of the subscription/card state returned by the backend.

### 10.4 Payment and subscription states

Payment statuses: - `pending`: processing or awaiting confirmation. -
`succeeded`: successful payment with receipt/invoice where available. -
`failed`: failed payment with an appropriate recovery action. -
`refunded`: refunded, with clear refund status and amount where
available.

Subscription UI must distinguish: - Active. - Cancellation scheduled at
period end. - Payment failure / past-due states if exposed by the API. -
Cancelled or ended. - Other states returned by the billing integration.

Do not invent exact subscription states that the API does not expose.
Render unknown states safely as an unavailable/needs-attention state
rather than silently mapping them to active.

### 10.5 Center status

  -----------------------------------------------------------------------
  Status                  Public directory        Center workspace
  ----------------------- ----------------------- -----------------------
  `pending`               Not publicly bookable   Show review/pending
                                                  state

  `active`                Visible                 Normal management

  `suspended`             Hidden; cannot receive  Show suspension
                          new bookings            reason/state and
                                                  permitted read-only
                                                  access
  -----------------------------------------------------------------------

Existing appointments remain retained when a center is suspended. Do not
erase or hide historical records as a side effect of the status
presentation.

### 10.6 Permission-aware actions

-   Render controls only when the relevant permission is present.
-   For sensitive operations, use a confirmation dialog with the exact
    target and consequence.
-   Handle authorization failures from the API even if the UI showed the
    action.
-   On permission loss, refresh or reconcile the visible interface
    without exposing protected data.
-   Never rely on disabled buttons as the authorization boundary.

### 10.7 Family and privacy

-   Only the owner can view or modify linked family-member data.
-   A family member belongs to exactly one owner in the MVP.
-   Do not reveal whether another user's family-member ID exists.
-   Mask National ID wherever it is displayed.
-   Center staff see only the minimum data needed for the appointment.
-   A family member with future appointments cannot be silently removed;
    show the blocking appointments or the supported resolution path
    without leaking unrelated data.

### 10.8 CMS content behavior

-   Public sections follow CMS active state and sort order.
-   Localized content must use the active locale with a defined
    fallback.
-   Unknown section types are skipped safely on the public page.
-   Empty or invalid optional content must not create broken layouts.
-   CMS preview is not proof that content has been published.

------------------------------------------------------------------------

## 11. Forms and validation

-   Use a consistent field anatomy: label, control, optional hint,
    inline error.
-   Mark required fields clearly and consistently.
-   Validate on blur or submit according to field type; avoid aggressive
    error messages while the user is still typing.
-   Preserve entered values after recoverable API errors.
-   Focus the first invalid field after submit.
-   Map backend validation errors to the correct field and localized
    message.
-   Distinguish validation errors from authorization, conflict, and
    server errors.
-   Confirm destructive changes and explain irreversible consequences.
-   Use appropriate input types and mobile keyboards for email, phone,
    password, date, and numeric values.
-   Do not ask for sensitive information unless the workflow requires
    it.
-   National ID must be masked and handled according to the PRD's
    privacy requirements.

### 11.1 Booking form

-   Keep the selected person visible throughout the flow.
-   Make branch and timezone explicit before selecting a time.
-   Show only server-provided slots.
-   Summarize all selections before submission.
-   Distinguish booking submission from booking confirmation.
-   On conflict, retain the person, center, doctor, and service
    selections where still valid.

### 11.2 Payment form

-   Use Stripe Checkout or Stripe Elements / Payment Element.
-   Never create custom raw card-number, CVC, or expiry inputs.
-   Show processing, success, and failure as separate states.
-   Do not issue or display an active membership based only on the
    browser redirect.

------------------------------------------------------------------------

## 12. Accessibility and usability requirements

Target **WCAG 2.2 AA** for new UI work, while retaining the PRD's WCAG
2.1 AA baseline.

-   Keyboard access for all interactive controls.
-   Visible focus indicators with sufficient contrast.
-   Minimum 44 × 44px touch targets where practical.
-   Semantic headings and landmarks.
-   Programmatic labels for every form control.
-   Error messages associated with their fields.
-   Status communicated by text and/or icon, never color alone.
-   Accessible names for icon-only controls.
-   Dialog focus management and focus restoration.
-   Screen-reader announcements for important asynchronous updates
    without announcing every background refresh.
-   Reduced-motion support.
-   Charts accompanied by text summaries or accessible data
    alternatives.
-   QR codes must have an adjacent human-readable card identity and
    status.
-   Do not rely on hover to reveal essential information.

Test Arabic and English with keyboard-only navigation and at least one
screen reader.

------------------------------------------------------------------------

## 13. Data visualization

Charts should answer a real operational question.

-   Use line charts for meaningful trends over time.
-   Use bar charts for comparisons across categories or periods.
-   Use compact progress indicators for bounded completion or capacity.
-   Use tables or lists when exact values matter more than visual
    pattern.
-   Label axes, units, date ranges, and series.
-   Do not use 3D charts, decorative gauges, or unexplained percentages.
-   Avoid red/green-only comparisons.
-   Provide empty and insufficient-data states.
-   Keep chart colors consistent with semantic tokens and distinguish
    series using more than color where necessary.

Potential chart use cases include booking volume and operational trends
only when the required data is actually available. Do not imply revenue
from center appointments because appointment payments to centers are out
of scope in the PRD.

------------------------------------------------------------------------

## 14. Feedback, notifications, and confirmations

Use the right feedback pattern for the severity and persistence of the
event.

  Pattern               Use
  --------------------- ----------------------------------------------------
  Inline success        A local action completed and context should remain
  Toast                 Brief, non-critical confirmation
  Inline alert          Persistent issue affecting the current view
  Dialog                High-impact decision or destructive action
  Status badge          Persistent state attached to a record
  Notification center   Events the user may need to revisit

Examples: - "تم إرسال طلب الحجز" must not say "تم تأكيد الموعد" until
confirmation is returned. - "جارٍ معالجة الدفع" must remain distinct from
"تم الدفع بنجاح". - A failed image upload should offer retry without
discarding the message draft. - A cancelled appointment should remain in
history with its cancelled status. - A suspended center should show the
effect on new bookings without implying existing bookings were deleted.

------------------------------------------------------------------------

## 15. Security-sensitive UI

The UI must support the PRD's security and privacy rules.

-   Never expose National ID in full in lists or routine profile views.
-   Never put sensitive personal data in URLs, logs, analytics events,
    or client-side error messages.
-   Do not expose billing details during center-side membership
    verification.
-   QR codes contain signed opaque tokens, not personal data.
-   Use short-lived signed media URLs as supplied by the backend.
-   Do not render private media or conversations to unauthorized users.
-   Provide clear session/device labels and a deliberate revoke-session
    action.
-   Sensitive admin actions (center suspension, refunds, role changes)
    require clear confirmation and must be represented in the activity
    log when the backend reports success.
-   Do not claim an operation was audited until the server confirms the
    operation.

------------------------------------------------------------------------

## 16. Implementation and documentation standards

### 16.1 Component API expectations

Components should receive explicit, typed data and state. Avoid
embedding business rules in visual components.

Recommended separation: - **Primitive:** visual and interaction
foundation. - **Pattern:** reusable composition, such as a status row or
record summary. - **Domain component:** Rifad-specific component that
understands the shape of a business object. - **Page composition:**
assembles widgets and workflows for a role and route.

Domain components may interpret backend states for presentation, but
must not replace backend validation or authorization.

### 16.2 Storybook / component documentation

Document components with: - Default state. - Loading, empty, error,
disabled, and success states as relevant. - RTL and LTR. - Narrow mobile
and desktop sizes. - Long Arabic text and mixed-script examples. -
Keyboard and focus behavior. - Permission-dependent actions. -
Business-state examples.

### 16.3 Visual and functional testing

Use Playwright for critical end-to-end and visual coverage.

Required coverage: - Arabic RTL and English LTR for critical pages. -
Mobile and desktop layouts. - Booking happy path and slot conflict. -
Cancellation cutoff and rescheduling behavior. - Membership
active/expired/suspended/cancelled presentation. - Payment
pending/succeeded/failed/refunded presentation. - Center
pending/active/suspended presentation. - Permission-hidden actions and
API authorization failures. - Family-member ownership and privacy
boundaries. - Chat reconnect and media upload failure states. - CMS
section ordering, activation, localization, and unknown section
behavior.

Use accessibility checks (for example axe) alongside manual keyboard and
screen-reader testing.

------------------------------------------------------------------------

## 17. Recommended screen inventory

This is the UI coverage map derived from the PRD. It is not a new
product scope.

### Public

-   Homepage with CMS-rendered sections.
-   Center directory and filters.
-   Center detail and branches.
-   Doctor detail and availability.
-   Membership package listing and detail.
-   Registration, login, verification, and password reset.

### Individual / Family

-   Home dashboard.
-   Profile and account settings.
-   Family member list and editor.
-   Appointment booking flow.
-   Appointment list, detail, and history.
-   Membership cards and card detail.
-   Subscription management.
-   Payment history and receipt.
-   Conversation list and chat thread.
-   Notification center.
-   Active sessions/devices.

### Medical Center

-   Center overview.
-   Center profile.
-   Branch list and editor.
-   Doctor list and editor.
-   Specialty and service management.
-   Availability rules and exceptions.
-   Appointment list, detail, and schedule.
-   Chat inbox and conversation.
-   Staff/role controls where exposed by the product.

### Administration

-   Platform overview.
-   Users.
-   Centers and approval review.
-   Packages and benefits.
-   CMS section list, editor, and preview.
-   Newsletter subscribers.
-   Roles and permissions.
-   Platform settings.
-   Activity log.

------------------------------------------------------------------------

## 18. Suggested design and implementation order

1.  **Foundations:** semantic tokens, typography, RTL/LTR, responsive
    shells, navigation, accessibility primitives.
2.  **Core primitives:** buttons, fields, overlays, badges, alerts,
    skeletons, empty/error states.
3.  **Shared domain patterns:** person identity, status badges, record
    summaries, filters, data tables.
4.  **Booking system:** PatientSelector, directory cards,
    BookingStepper, SlotPicker, BookingSummary, AppointmentCard.
5.  **Membership and billing:** MembershipCard, coverage, PackageCard,
    SubscriptionStatus, PaymentRecord, processing states.
6.  **Center operations:** schedule, appointment triage, branch context,
    doctor/service management.
7.  **Communication:** conversation list, message thread, media states,
    notifications.
8.  **Administration and CMS:** tables, review panels, schema-driven
    section editor, activity log.
9.  **Polish and validation:** visual regression, accessibility,
    responsive edge cases, RTL/LTR, and business-state testing.

Prioritize the booking and membership workflows because they are central
to the product's success metrics and trust model. Build dashboard
widgets around actual tasks and available data rather than designing a
decorative dashboard first.

------------------------------------------------------------------------

## 19. Non-negotiable design rules

1.  The UI never overrides backend business logic.
2.  A booking is not confirmed until the backend confirms it.
3.  A payment redirect is not proof of payment or membership activation.
4.  A slot is never invented or assumed available by the frontend.
5.  A family member's data is visible only to the owning account,
    subject to the PRD's access rules.
6.  A center sees only the minimum family-member data required for a
    booking.
7.  A suspended center cannot receive new bookings and is hidden from
    public discovery; existing appointments remain retained.
8.  Membership status and coverage come from authoritative
    subscription/card state.
9.  Permission-based navigation is not an authorization mechanism.
10. Every data-driven widget has appropriate loading, empty, error, and
    stale/partial behavior.
11. Arabic RTL and English LTR are both first-class layouts.
12. Every primary action has a clear result, failure state, and recovery
    path.

------------------------------------------------------------------------

**End of Rifad UI Design System v2.0**

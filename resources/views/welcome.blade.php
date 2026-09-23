<x-layouts.public title="Welcome" :fullWidth="true">

    {{-- ══════════════════════════════════════════════════════════════
         HERO SECTION
         Full-bleed cover image with overlay, school identity, and dual CTAs
    ══════════════════════════════════════════════════════════════ --}}
    <section class="landing-hero" aria-labelledby="hero-heading">
        <img
            src="{{ asset('images/landing_page_images/cover_pic.png') }}"
            alt=""
            aria-hidden="true"
            class="landing-hero__bg"
        >
        <div class="landing-hero__overlay" aria-hidden="true"></div>

        <div class="landing-hero__content">
            <div class="landing-hero__badge" aria-hidden="true">
                <svg class="landing-hero__badge-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.06l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                Now accepting enrollment applications
            </div>

            <img
                src="{{ asset('images/logo/jca_logo.png') }}"
                alt=""
                aria-hidden="true"
                class="landing-hero__logo"
            >

            <p class="landing-hero__school-name">JCSGO Christian Academy R1-Inc.</p>
            <h1 id="hero-heading" class="landing-hero__heading">
                Student Enrollment<br class="hidden sm:inline"> & Management System
            </h1>
            <p class="landing-hero__subtitle">
                A modern, secure portal for families and school staff — making enrollment clearer, records safer, and communication easier.
            </p>

            <div class="landing-hero__actions">
                <a href="{{ route('enrollment.public.start') }}" class="landing-cta landing-cta--apply" id="cta-apply">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 14h-3v3h-2v-3H8v-2h3v-3h2v3h3v2zm-3-7V3.5L18.5 9H13z"/></svg>
                    Apply for Enrollment
                </a>
                <a href="{{ route('identity.login') }}" class="landing-cta landing-cta--guardian" id="cta-guardian-login">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M7.5 6.5C7.5 8.981 9.519 11 12 11s4.5-2.019 4.5-4.5S14.481 2 12 2 7.5 4.019 7.5 6.5zM20 21h1.5v-1.5c0-3.426-2.574-6-6-6h-7c-3.426 0-6 2.574-6 6V21H20z"/></svg>
                    Guardian / Student Sign In
                </a>
                <a href="{{ route('identity.staff.login') }}" class="landing-cta landing-cta--staff" id="cta-staff-login">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/></svg>
                    Admin / Staff Sign In
                </a>
            </div>
        </div>

        {{-- Scroll hint --}}
        <div class="landing-hero__scroll" aria-hidden="true">
            <span>Discover more</span>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 011.06 0L10 11.94l3.72-3.72a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-1.06 0L5.22 9.28a.75.75 0 010-1.06z" clip-rule="evenodd"/></svg>
        </div>
    </section>

    {{-- ══════════════════════════════════════════════════════════════
         PURPOSE / INTRO
    ══════════════════════════════════════════════════════════════ --}}
    <section class="landing-section landing-intro" aria-labelledby="intro-heading">
        <div class="landing-container">
            <p class="landing-section__eyebrow">Built for JCA</p>
            <h2 id="intro-heading" class="landing-section__heading">
                Everything your school needs,<br class="hidden lg:inline"> in one secure place
            </h2>
            <p class="landing-section__description">
                From the moment a family submits an application to the day a student is enrolled and placed in a section —
                this system keeps every step organized, transparent, and private.
            </p>
        </div>
    </section>

    {{-- ══════════════════════════════════════════════════════════════
         FEATURES / BENEFITS GRID
    ══════════════════════════════════════════════════════════════ --}}
    <section class="landing-section" aria-labelledby="features-heading">
        <div class="landing-container">
            <p class="landing-section__eyebrow">Key Features</p>
            <h2 id="features-heading" class="landing-section__heading">
                What this system offers
            </h2>

            <div class="landing-features">
                {{-- Feature 1 --}}
                <div class="landing-feature-card">
                    <div class="landing-feature-card__icon landing-feature-card__icon--navy">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="28" height="28"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm2 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                    </div>
                    <h3 class="landing-feature-card__title">Online Enrollment</h3>
                    <p class="landing-feature-card__text">
                        Families apply online with guided forms. Upload documents securely, track application status in real time, and never wonder where things stand.
                    </p>
                </div>

                {{-- Feature 2 --}}
                <div class="landing-feature-card">
                    <div class="landing-feature-card__icon landing-feature-card__icon--gold">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="28" height="28"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                    </div>
                    <h3 class="landing-feature-card__title">Private & Secure</h3>
                    <p class="landing-feature-card__text">
                        Sensitive student records, support documents, and financial data are protected with role-based access. Only authorized people see what they need.
                    </p>
                </div>

                {{-- Feature 3 --}}
                <div class="landing-feature-card">
                    <div class="landing-feature-card__icon landing-feature-card__icon--orange">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="28" height="28"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    </div>
                    <h3 class="landing-feature-card__title">Guardian & Student Portals</h3>
                    <p class="landing-feature-card__text">
                        Parents see their children's enrollment progress, requirements, section, and financial summary. Students in Grades 7–12 get their own portal too.
                    </p>
                </div>

                {{-- Feature 4 --}}
                <div class="landing-feature-card">
                    <div class="landing-feature-card__icon landing-feature-card__icon--navy">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="28" height="28"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                    </div>
                    <h3 class="landing-feature-card__title">Payment Tracking</h3>
                    <p class="landing-feature-card__text">
                        Accounting manages tuition fees, payment schedules, discounts, and exam permits — all tracked with reliable, auditable records.
                    </p>
                </div>

                {{-- Feature 5 --}}
                <div class="landing-feature-card">
                    <div class="landing-feature-card__icon landing-feature-card__icon--gold">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="28" height="28"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                    </div>
                    <h3 class="landing-feature-card__title">Section Placement</h3>
                    <p class="landing-feature-card__text">
                        After approval, students are placed into sections with capacity management. Staff can balance groups, track transfers, and manage promotions.
                    </p>
                </div>

                {{-- Feature 6 --}}
                <div class="landing-feature-card">
                    <div class="landing-feature-card__icon landing-feature-card__icon--orange">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="28" height="28"><path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/></svg>
                    </div>
                    <h3 class="landing-feature-card__title">SMS & Announcements</h3>
                    <p class="landing-feature-card__text">
                        Send targeted announcements and SMS notifications for enrollment status changes, missing requirements, and school-wide updates.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════════════════════════
         HOW IT WORKS — ENROLLMENT STEPS
    ══════════════════════════════════════════════════════════════ --}}
    <section class="landing-section landing-steps-section" aria-labelledby="steps-heading">
        <div class="landing-container">
            <p class="landing-section__eyebrow">How It Works</p>
            <h2 id="steps-heading" class="landing-section__heading">
                A clear path from application to enrollment
            </h2>

            <div class="landing-steps">
                <div class="landing-step">
                    <div class="landing-step__number">1</div>
                    <h3 class="landing-step__title">Apply</h3>
                    <p class="landing-step__text">Submit an online application and upload required documents securely.</p>
                </div>
                <div class="landing-step__connector" aria-hidden="true"></div>
                <div class="landing-step">
                    <div class="landing-step__number">2</div>
                    <h3 class="landing-step__title">Review</h3>
                    <p class="landing-step__text">The registrar verifies documents, checks requirements, and reviews eligibility.</p>
                </div>
                <div class="landing-step__connector" aria-hidden="true"></div>
                <div class="landing-step">
                    <div class="landing-step__number">3</div>
                    <h3 class="landing-step__title">Approve</h3>
                    <p class="landing-step__text">Once complete, the application is approved and fee assessment is prepared.</p>
                </div>
                <div class="landing-step__connector" aria-hidden="true"></div>
                <div class="landing-step">
                    <div class="landing-step__number">4</div>
                    <h3 class="landing-step__title">Enroll</h3>
                    <p class="landing-step__text">The student is placed in a section and officially enrolled for the school year.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════════════════════════
         PHOTO GALLERY
    ══════════════════════════════════════════════════════════════ --}}
    <section class="landing-section" aria-labelledby="gallery-heading">
        <div class="landing-container">
            <p class="landing-section__eyebrow">Our Community</p>
            <h2 id="gallery-heading" class="landing-section__heading">
                Life at JCA
            </h2>

            <div class="landing-gallery">
                <div class="landing-gallery__item landing-gallery__item--wide">
                    <img
                        src="{{ asset('images/landing_page_images/JCA1.jpg') }}"
                        alt="JCA students at the school chapel during announcements"
                        loading="lazy"
                    >
                </div>
                <div class="landing-gallery__item">
                    <img
                        src="{{ asset('images/landing_page_images/JCA 5.jpg') }}"
                        alt="Elementary students posing on the first day of school 2026"
                        loading="lazy"
                    >
                </div>
                <div class="landing-gallery__item">
                    <img
                        src="{{ asset('images/landing_page_images/JCA3.jpg') }}"
                        alt="Junior high school students celebrating with balloons"
                        loading="lazy"
                    >
                </div>
                <div class="landing-gallery__item landing-gallery__item--wide">
                    <img
                        src="{{ asset('images/landing_page_images/JCA2.jpg') }}"
                        alt="JCA students participating in a school assembly"
                        loading="lazy"
                    >
                </div>
                <div class="landing-gallery__item landing-gallery__item--tall" hidden>
                    <img
                        src="{{ asset('images/landing_page_images/kids_no_bg.png') }}"
                        alt="Two young JCA students on their first day of school"
                        loading="lazy"
                        class="landing-gallery__cutout"
                    >
                </div>
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════════════════════════
         ROLES AT A GLANCE
    ══════════════════════════════════════════════════════════════ --}}
    <section class="landing-section landing-roles-section" aria-labelledby="roles-heading">
        <div class="landing-container">
            <p class="landing-section__eyebrow">For Everyone</p>
            <h2 id="roles-heading" class="landing-section__heading">
                Designed for every role in the school
            </h2>

            <div class="landing-roles">
                <div class="landing-role-card">
                    <div class="landing-role-card__icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="32" height="32"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"/></svg>
                    </div>
                    <h3 class="landing-role-card__title">Parents & Guardians</h3>
                    <p class="landing-role-card__text">Apply for enrollment, upload requirements, track progress, view financial summary, and receive school updates.</p>
                </div>
                <div class="landing-role-card">
                    <div class="landing-role-card__icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="32" height="32"><path d="M5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82zM12 3L1 9l11 6 9-4.91V17h2V9L12 3z"/></svg>
                    </div>
                    <h3 class="landing-role-card__title">Students</h3>
                    <p class="landing-role-card__text">Grades 7–12 can apply directly, view their enrollment status, section assignment, schedule, and school announcements.</p>
                </div>
                <div class="landing-role-card">
                    <div class="landing-role-card__icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="32" height="32"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/></svg>
                    </div>
                    <h3 class="landing-role-card__title">Admin & Staff</h3>
                    <p class="landing-role-card__text">Registrar, accounting, and admin manage applications, verify documents, track payments, assign sections, and oversee the entire enrollment process.</p>
                </div>
                <div class="landing-role-card">
                    <div class="landing-role-card__icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="32" height="32"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12zM7 9h2v2H7zm4 0h2v2h-2zm4 0h2v2h-2z"/></svg>
                    </div>
                    <h3 class="landing-role-card__title">Teachers</h3>
                    <p class="landing-role-card__text">View assigned sections and students, access approved educational support summaries, and stay connected through announcements.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════════════════════════
         BOTTOM CTA
    ══════════════════════════════════════════════════════════════ --}}
    <section class="landing-section landing-bottom-cta" aria-labelledby="bottom-cta-heading">
        <div class="landing-container">
            <div class="landing-bottom-cta__card">
                <img
                    src="{{ asset('images/logo/jca_logo.png') }}"
                    alt=""
                    aria-hidden="true"
                    class="landing-bottom-cta__logo"
                >
                <h2 id="bottom-cta-heading" class="landing-bottom-cta__heading">
                    Ready to get started?
                </h2>
                <p class="landing-bottom-cta__text">
                    New family? Apply online for Preschool through Grade 6. Already registered? Sign in to check your child's status or manage school records.
                </p>
                <div class="landing-hero__actions">
                    <a href="{{ route('enrollment.public.start') }}" class="landing-cta landing-cta--apply" id="cta-apply-bottom">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 14h-3v3h-2v-3H8v-2h3v-3h2v3h3v2zm-3-7V3.5L18.5 9H13z"/></svg>
                        Apply for Enrollment
                    </a>
                    <a href="{{ route('identity.login') }}" class="landing-cta landing-cta--guardian" id="cta-guardian-login-bottom">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M7.5 6.5C7.5 8.981 9.519 11 12 11s4.5-2.019 4.5-4.5S14.481 2 12 2 7.5 4.019 7.5 6.5zM20 21h1.5v-1.5c0-3.426-2.574-6-6-6h-7c-3.426 0-6 2.574-6 6V21H20z"/></svg>
                        Guardian / Student Sign In
                    </a>
                    <a href="{{ route('identity.staff.login') }}" class="landing-cta landing-cta--staff" id="cta-staff-login-bottom">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/></svg>
                        Admin / Staff Sign In
                    </a>
                </div>
            </div>
        </div>
    </section>

</x-layouts.public>

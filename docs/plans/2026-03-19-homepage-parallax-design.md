# Homepage Parallax Redesign — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers-extended-cc:executing-plans to implement this plan task-by-task.

**Goal:** Replace the current plain homepage with an Apple-style cinematic parallax experience showcasing OpenDispatch through an iPhone 15 Pro CSS mockup.

**Architecture:** A single Stimulus `parallax_controller` tracks scroll position and sets CSS custom properties + a `data-act` attribute on the container. All visual transitions are driven by CSS using these attributes. The iPhone is `position: sticky` inside a 400vh scroll container. Act 5 (ecosystem) is normal flow below.

**Tech Stack:** Stimulus controller, pure CSS animations, inline SVG icons, Google Fonts (Outfit), AssetMapper.

---

### Task 0: Setup — Base Template, Font, Controller Update

**Files:**
- Modify: `templates/base.html.twig`
- Modify: `src/Controller/Public/HomeController.php`
- Create: `assets/styles/homepage.css`

**Step 1: Update base.html.twig**

Add Google Fonts, `body_class` block, and `content_wrapper` block:

```twig
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{% block title %}OpenDispatch{% endblock %}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    {% block stylesheets %}{% endblock %}
    {% block importmap %}{{ importmap('app') }}{% endblock %}
</head>
<body class="{% block body_class %}{% endblock %}">
    <nav class="site-nav">
        <a href="{{ path('app_home') }}" class="site-nav-brand">OpenDispatch</a>
        <div class="site-nav-links">
            <a href="{{ path('app_skills') }}">Skills</a>
            <a href="{{ path('app_docs_index') }}">Docs</a>
            {% if app.user %}
                <a href="{{ path('app_admin_dashboard') }}">Admin</a>
                <a href="{{ path('app_logout') }}">Logout</a>
            {% endif %}
        </div>
    </nav>

    {% block content_wrapper %}
    <div class="container">
        {% block body %}{% endblock %}
    </div>
    {% endblock %}

    {% block javascripts %}{% endblock %}
</body>
</html>
```

**Step 2: Update HomeController — remove download_count**

```php
#[Route('/', name: 'app_home')]
public function index(
    SkillRepository $skillRepository,
): Response {
    return $this->render('public/home.html.twig', [
        'skill_count' => count($skillRepository->findAll()),
    ]);
}
```

Remove the `SkillDownloadRepository` import and constructor parameter.

**Step 3: Create empty homepage.css**

Create `assets/styles/homepage.css` with a comment header:

```css
/* ==============================================
   OpenDispatch Homepage — Parallax Showcase
   ============================================== */
```

**Step 4: Verify**

Run `php bin/console cache:clear` and load the site. Existing pages should look identical. The homepage should still render (it will break slightly since `download_count` is removed from the template — that's OK, we rewrite it in Task 3).

**Step 5: Commit**

```bash
git add templates/base.html.twig src/Controller/Public/HomeController.php assets/styles/homepage.css
git commit -m "chore: prepare base template and controller for homepage redesign"
```

---

### Task 1: iPhone 15 Pro CSS Mockup

**Files:**
- Modify: `assets/styles/homepage.css`

**Step 1: Add the iPhone CSS to homepage.css**

This builds a realistic iPhone 15 Pro entirely in CSS — titanium frame, Dynamic Island, side buttons, Action Button with pulsing glow arrow, and a screen area for content.

```css
/* ==============================================
   OpenDispatch Homepage — Parallax Showcase
   ============================================== */

/* --- iPhone 15 Pro Mockup --- */
.iphone-wrapper {
    position: relative;
    z-index: 10;
}

.iphone {
    position: relative;
    width: 280px;
    height: 572px;
    background: #1c1c1e;
    border-radius: 52px;
    border: 2.5px solid #3a3a3c;
    box-shadow:
        inset 0 0 0 1px rgba(255, 255, 255, 0.05),
        0 0 0 1px rgba(0, 0, 0, 0.3),
        0 20px 60px -10px rgba(0, 0, 0, 0.7),
        0 40px 100px -20px rgba(0, 0, 0, 0.5);
}

.iphone-screen {
    position: absolute;
    top: 6px;
    left: 6px;
    right: 6px;
    bottom: 6px;
    background: #000;
    border-radius: 46px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

.iphone-dynamic-island {
    position: absolute;
    top: 10px;
    left: 50%;
    transform: translateX(-50%);
    width: 120px;
    height: 34px;
    background: #000;
    border-radius: 17px;
    z-index: 10;
}

/* --- Side Buttons --- */
.iphone-btn {
    position: absolute;
    background: #3a3a3c;
}

.iphone-action-btn {
    left: -3px;
    top: 110px;
    width: 3px;
    height: 30px;
    border-radius: 2px 0 0 2px;
    z-index: 5;
}

.iphone-vol-up {
    left: -3px;
    top: 158px;
    width: 3px;
    height: 30px;
    border-radius: 2px 0 0 2px;
}

.iphone-vol-down {
    left: -3px;
    top: 196px;
    width: 3px;
    height: 30px;
    border-radius: 2px 0 0 2px;
}

.iphone-power {
    right: -3px;
    top: 158px;
    width: 3px;
    height: 48px;
    border-radius: 0 2px 2px 0;
}

/* --- Action Button Glow + Arrow --- */
.action-arrow {
    position: absolute;
    left: -44px;
    top: 113px;
    display: flex;
    align-items: center;
    gap: 4px;
    animation: arrow-pulse 2.5s ease-in-out infinite;
}

.action-arrow svg {
    width: 20px;
    height: 20px;
    fill: none;
    stroke: #2563eb;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
    filter: drop-shadow(0 0 6px rgba(37, 99, 235, 0.5));
}

.action-btn-glow {
    position: absolute;
    inset: -6px;
    border-radius: 4px 0 0 4px;
    background: radial-gradient(circle, rgba(37, 99, 235, 0.4), transparent 70%);
    animation: glow-pulse 2.5s ease-in-out infinite;
}

@keyframes arrow-pulse {
    0%, 100% { opacity: 0.4; transform: translateX(0); }
    50% { opacity: 1; transform: translateX(4px); }
}

@keyframes glow-pulse {
    0%, 100% { opacity: 0.2; }
    50% { opacity: 0.8; }
}

/* --- Screen States --- */
.screen-state {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.6s ease;
}

/* Screen off: subtle sheen */
.screen-off {
    opacity: 1;
    background: linear-gradient(
        135deg,
        rgba(255, 255, 255, 0.01) 0%,
        rgba(255, 255, 255, 0.03) 50%,
        rgba(255, 255, 255, 0.01) 100%
    );
}

/* Screen wake: radial glow */
.screen-wake {
    background: radial-gradient(
        circle at center,
        rgba(37, 99, 235, 0.12) 0%,
        rgba(37, 99, 235, 0.04) 40%,
        transparent 70%
    );
}

/* Listening: concentric rings */
.screen-listen {
    flex-direction: column;
}

.listen-ring {
    position: absolute;
    border: 1.5px solid rgba(37, 99, 235, 0.4);
    border-radius: 50%;
    animation: ring-expand 2.2s ease-out infinite;
}

.listen-ring:nth-child(1) { width: 50px; height: 50px; animation-delay: 0s; }
.listen-ring:nth-child(2) { width: 90px; height: 90px; animation-delay: 0.35s; }
.listen-ring:nth-child(3) { width: 130px; height: 130px; animation-delay: 0.7s; }

@keyframes ring-expand {
    0% { transform: scale(0.8); opacity: 0.7; }
    100% { transform: scale(1.5); opacity: 0; }
}

/* Result card */
.screen-result {
    flex-direction: column;
}

.result-card {
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 24px 32px;
    text-align: center;
    backdrop-filter: blur(10px);
}

.result-icon {
    width: 44px;
    height: 44px;
    background: #16a34a;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
    font-size: 20px;
    color: white;
}

.result-label {
    font-family: 'Outfit', sans-serif;
    font-size: 17px;
    font-weight: 600;
    color: #f5f5f7;
    margin-bottom: 2px;
}

.result-action {
    font-size: 13px;
    color: rgba(255, 255, 255, 0.45);
}
```

**Step 2: Verify**

Create a temporary test page or add the iPhone HTML to the homepage template to verify the mockup renders correctly. The phone should look like a convincing iPhone 15 Pro outline on a dark background.

**Step 3: Commit**

```bash
git add assets/styles/homepage.css
git commit -m "feat: add iPhone 15 Pro CSS mockup for homepage"
```

---

### Task 2: Parallax Stimulus Controller

**Files:**
- Create: `assets/controllers/parallax_controller.js`

**Step 1: Create the controller**

```javascript
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['track', 'phone'];

    connect() {
        this._onScroll = this._handleScroll.bind(this);
        this._onResize = this._handleResize.bind(this);

        window.addEventListener('scroll', this._onScroll, { passive: true });
        window.addEventListener('resize', this._onResize, { passive: true });

        this._ticking = false;
        this._handleResize();
        this._handleScroll();
    }

    disconnect() {
        window.removeEventListener('scroll', this._onScroll);
        window.removeEventListener('resize', this._onResize);
    }

    _handleResize() {
        this._vh = window.innerHeight;
    }

    _handleScroll() {
        if (this._ticking) return;
        this._ticking = true;

        requestAnimationFrame(() => {
            this._ticking = false;
            this._update();
        });
    }

    _update() {
        const track = this.trackTarget;
        const rect = track.getBoundingClientRect();
        const trackScrollable = track.offsetHeight - this._vh;

        // How far through the parallax track we've scrolled (0 → 1)
        const trackProgress = Math.max(0, Math.min(1, -rect.top / trackScrollable));

        const actCount = 4;
        const rawAct = trackProgress * actCount;
        const currentAct = Math.min(Math.floor(rawAct) + 1, actCount);
        const actProgress = rawAct % 1;

        // Set the current act as a data attribute for CSS selectors
        this.element.dataset.act = currentAct;
        this.element.style.setProperty('--act-progress', actProgress.toFixed(3));
        this.element.style.setProperty('--track-progress', trackProgress.toFixed(3));

        // Per-act progress (0→1 within each act's scroll range)
        for (let i = 1; i <= actCount; i++) {
            const p = Math.max(0, Math.min(1, rawAct - (i - 1)));
            this.element.style.setProperty(`--act-${i}`, p.toFixed(3));
        }

        // Hide/show action arrow based on act
        if (this.hasPhoneTarget) {
            const phone = this.phoneTarget;
            // Action button press effect in act 2
            const actionBtn = phone.querySelector('.iphone-action-btn');
            if (actionBtn) {
                const pressDepth = currentAct >= 2 ? 1 : parseFloat(this.element.style.getPropertyValue('--act-1')) || 0;
                actionBtn.style.setProperty('--press', pressDepth.toFixed(3));
            }
        }
    }
}
```

**Step 2: Verify**

The controller should auto-register via Stimulus bundle (file naming convention `parallax_controller.js` → `data-controller="parallax"`). Clear cache and verify no JS console errors.

**Step 3: Commit**

```bash
git add assets/controllers/parallax_controller.js
git commit -m "feat: add parallax scroll Stimulus controller"
```

---

### Task 3: Homepage Template — All 5 Acts

**Files:**
- Rewrite: `templates/public/home.html.twig`

**Step 1: Replace the entire template**

```twig
{% extends 'base.html.twig' %}

{% block title %}OpenDispatch — Automate Your iPhone with Natural Language{% endblock %}

{% block body_class %}homepage{% endblock %}

{% block stylesheets %}
    <link rel="stylesheet" href="{{ asset('styles/homepage.css') }}">
{% endblock %}

{% block content_wrapper %}
<div class="homepage" data-controller="parallax">

    {# ===== PARALLAX SECTION (Acts 1–4) ===== #}
    <div class="parallax-track" data-parallax-target="track">
        <div class="parallax-viewport">

            {# --- iPhone Device --- #}
            <div class="iphone-wrapper" data-parallax-target="phone">
                {# Action Button arrow indicator #}
                <div class="action-arrow">
                    <svg viewBox="0 0 24 24"><path d="M5 12h11M12 5l7 7-7 7"/></svg>
                </div>

                <div class="iphone">
                    {# Side buttons #}
                    <div class="iphone-btn iphone-action-btn">
                        <div class="action-btn-glow"></div>
                    </div>
                    <div class="iphone-btn iphone-vol-up"></div>
                    <div class="iphone-btn iphone-vol-down"></div>
                    <div class="iphone-btn iphone-power"></div>

                    {# Screen #}
                    <div class="iphone-screen">
                        <div class="iphone-dynamic-island"></div>

                        {# State 1: Off #}
                        <div class="screen-state screen-off"></div>

                        {# State 2: Waking up #}
                        <div class="screen-state screen-wake"></div>

                        {# State 3: Listening #}
                        <div class="screen-state screen-listen">
                            <div class="listen-ring"></div>
                            <div class="listen-ring"></div>
                            <div class="listen-ring"></div>
                        </div>

                        {# State 4: Result #}
                        <div class="screen-state screen-result">
                            <div class="result-card">
                                <div class="result-icon">
                                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                                <div class="result-label">Tesla</div>
                                <div class="result-action">Trunk: Opening</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {# --- Text Overlays --- #}

            {# Act 1: Hero #}
            <div class="act-text act-1-text">
                <h1 class="hero-title">OpenDispatch</h1>
                <p class="hero-tagline">Automate your iPhone with natural language.</p>
            </div>

            {# Act 2: The Press #}
            <div class="act-text act-2-text">
                <p class="act-caption">Press. Speak. Done.</p>
            </div>

            {# Act 3: Listening #}
            <div class="act-text act-3-text">
                <div class="speech-bubble">"Open my Tesla trunk"</div>
            </div>

            {# Act 4: It Happens #}
            <div class="act-text act-4-text">
                <div class="tesla-trunk">
                    <svg class="tesla-svg" viewBox="0 0 320 200" fill="none">
                        {# Car body rear view #}
                        <path d="M50 155 L50 90 Q50 60 85 52 L235 52 Q270 60 270 90 L270 155"
                              stroke="rgba(255,255,255,0.15)" stroke-width="1.5"/>
                        {# Ground line #}
                        <line x1="30" y1="170" x2="290" y2="170" stroke="rgba(255,255,255,0.06)" stroke-width="1"/>
                        {# Wheels #}
                        <ellipse cx="90" cy="160" rx="22" ry="14" stroke="rgba(255,255,255,0.12)" stroke-width="1.5" fill="none"/>
                        <ellipse cx="230" cy="160" rx="22" ry="14" stroke="rgba(255,255,255,0.12)" stroke-width="1.5" fill="none"/>
                        {# Tail lights #}
                        <path d="M55 95 Q55 120 55 125 Q78 123 78 95" stroke="#e31937" stroke-width="1.5" fill="rgba(227,25,55,0.08)"/>
                        <path d="M265 95 Q265 120 265 125 Q242 123 242 95" stroke="#e31937" stroke-width="1.5" fill="rgba(227,25,55,0.08)"/>
                        {# Light bar #}
                        <line x1="78" y1="100" x2="242" y2="100" stroke="rgba(227,25,55,0.25)" stroke-width="0.75"/>
                        {# Trunk lid - animated #}
                        <g class="trunk-lid">
                            <path d="M85 52 Q160 25 235 52" stroke="rgba(255,255,255,0.2)" stroke-width="1.5" fill="rgba(255,255,255,0.02)"/>
                        </g>
                    </svg>
                </div>
            </div>

            {# Scroll indicator #}
            <div class="scroll-indicator">
                <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 9l6 6 6-6"/>
                </svg>
            </div>

        </div>
    </div>

    {# ===== ECOSYSTEM SECTION (Act 5) ===== #}
    <section class="ecosystem-section">
        <div class="ecosystem-inner">
            <h2 class="ecosystem-title">One app. Endless automations.</h2>
            <p class="ecosystem-subtitle">{{ skill_count }} skills and counting.</p>

            <div class="skill-showcase">
                <div class="showcase-card">
                    <div class="showcase-icon showcase-icon--tesla">
                        <svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor">
                            <path d="M12 2C6.48 2 2 3.58 2 4.5V6c0 .93 4.48 2.5 10 2.5S22 6.93 22 6V4.5C22 3.58 17.52 2 12 2zm0 20l-4-8h2.5V8h3v6H16l-4 8z"/>
                        </svg>
                    </div>
                    <h3>Tesla</h3>
                    <p class="showcase-command">"Open my trunk"</p>
                </div>

                <div class="showcase-card">
                    <div class="showcase-icon showcase-icon--calendar">
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/>
                        </svg>
                    </div>
                    <h3>Calendar</h3>
                    <p class="showcase-command">"What's my next meeting?"</p>
                </div>

                <div class="showcase-card">
                    <div class="showcase-icon showcase-icon--ticktick">
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
                        </svg>
                    </div>
                    <h3>TickTick</h3>
                    <p class="showcase-command">"Add buy groceries to my list"</p>
                </div>

                <div class="showcase-card">
                    <div class="showcase-icon showcase-icon--hue">
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 18h6M10 22h4M12 2v1M4.22 4.22l.71.71M1 12h1M4.22 19.78l.71-.71M18.36 4.93l.71-.71M23 12h-1M19.07 19.07l.71.71"/><path d="M15 13a3 3 0 10-6 0 3 3 0 003 3h0a3 3 0 003-3z"/><path d="M12 6a6 6 0 00-6 6c0 2.22 1.21 4.16 3 5.19V18a1 1 0 001 1h4a1 1 0 001-1v-.81A6 6 0 0012 6z"/>
                        </svg>
                    </div>
                    <h3>Hue</h3>
                    <p class="showcase-command">"Turn the living room blue"</p>
                </div>

                <div class="showcase-card">
                    <div class="showcase-icon showcase-icon--teams">
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
                            <path d="M19.5 6.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3zm-3 1h4a2 2 0 012 2v4.5a1.5 1.5 0 01-3 0V10h-3V7.5zM14 5a2 2 0 100-4 2 2 0 000 4zm-4 2h8a2 2 0 012 2v5a4 4 0 01-4 4h-4a4 4 0 01-4-4V9a2 2 0 012-2zm-5.5 0a1.5 1.5 0 100-3 1.5 1.5 0 000 3zm-3 2a1 1 0 011-1h3V10H2v4a3 3 0 003 3h.5v-2.5a4.5 4.5 0 01-4-4.5z"/>
                        </svg>
                    </div>
                    <h3>Microsoft Teams</h3>
                    <p class="showcase-command">"Set my status to away"</p>
                </div>
            </div>

            <a href="{{ path('app_skills') }}" class="browse-link">Browse all skills</a>
        </div>
    </section>

</div>
{% endblock %}
```

**Step 2: Verify**

Load homepage — it should show the iPhone mockup and all sections (unstyled text overlays and ecosystem). The parallax controller should be active (check console for `data-act` changing on scroll).

**Step 3: Commit**

```bash
git add templates/public/home.html.twig
git commit -m "feat: add homepage template with five-act parallax structure"
```

---

### Task 4: Homepage CSS — Parallax, Acts 1–4

**Files:**
- Modify: `assets/styles/homepage.css`

**Step 1: Add dark theme, parallax layout, and act transition CSS**

Append to `assets/styles/homepage.css` after the iPhone mockup CSS:

```css
/* --- Dark Theme Override (homepage only) --- */
body.homepage {
    background: #000;
    color: #f5f5f7;
    overflow-x: hidden;
}

body.homepage .site-nav {
    background: transparent;
    border-bottom: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 100;
}

body.homepage .site-nav-brand { color: #f5f5f7; }
body.homepage .site-nav-links a { color: rgba(255, 255, 255, 0.5); }
body.homepage .site-nav-links a:hover { color: #f5f5f7; text-decoration: none; }

/* --- Homepage Wrapper --- */
.homepage {
    font-family: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif;
}

/* --- Parallax Track --- */
.parallax-track {
    position: relative;
    height: 400vh;
}

.parallax-viewport {
    position: sticky;
    top: 0;
    height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

/* --- Act Text Overlays --- */
.act-text {
    position: absolute;
    text-align: center;
    left: 0;
    right: 0;
    opacity: 0;
    transform: translateY(20px);
    transition: opacity 0.6s ease, transform 0.6s ease;
    pointer-events: none;
}

/* Position text below the phone */
.act-1-text { bottom: 12%; }
.act-2-text { bottom: 14%; }
.act-3-text { bottom: 14%; }
.act-4-text { bottom: 6%; }

/* Show current act's text */
[data-act="1"] .act-1-text,
[data-act="2"] .act-2-text,
[data-act="3"] .act-3-text,
[data-act="4"] .act-4-text {
    opacity: 1;
    transform: translateY(0);
}

/* --- Act 1: Hero --- */
.hero-title {
    font-size: clamp(2.5rem, 5vw, 4.5rem);
    font-weight: 700;
    letter-spacing: -0.035em;
    line-height: 1.05;
    margin-bottom: 0.75rem;
    background: linear-gradient(to bottom, #fff, rgba(255, 255, 255, 0.65));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.hero-tagline {
    font-size: clamp(1rem, 2vw, 1.35rem);
    font-weight: 300;
    color: rgba(255, 255, 255, 0.45);
    max-width: 480px;
    margin: 0 auto;
    line-height: 1.5;
}

/* Show action arrow only in Act 1 */
[data-act="1"] .action-arrow { opacity: 1; }
[data-act="2"] .action-arrow,
[data-act="3"] .action-arrow,
[data-act="4"] .action-arrow { opacity: 0; transition: opacity 0.3s ease; }

/* Show action button glow only in Acts 1-2 */
[data-act="3"] .action-btn-glow,
[data-act="4"] .action-btn-glow { opacity: 0; }

/* --- Act 2: The Press --- */
.act-caption {
    font-size: clamp(1.5rem, 3vw, 2.5rem);
    font-weight: 600;
    letter-spacing: -0.02em;
    color: rgba(255, 255, 255, 0.85);
}

/* Action button press effect */
[data-act="2"] .iphone-action-btn {
    background: #2a2a2c;
    box-shadow: inset 0 0 4px rgba(37, 99, 235, 0.3);
}

/* --- Act 3: Listening --- */
.speech-bubble {
    display: inline-block;
    font-size: clamp(1.1rem, 2vw, 1.5rem);
    font-weight: 300;
    font-style: italic;
    color: rgba(255, 255, 255, 0.6);
    padding: 0.875rem 2rem;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 24px;
    background: rgba(255, 255, 255, 0.02);
}

/* --- Act 4: It Happens --- */
.tesla-trunk {
    width: 320px;
    max-width: 80vw;
    margin: 0 auto;
}

.tesla-svg {
    width: 100%;
    height: auto;
}

/* Trunk lid opens in Act 4 */
.trunk-lid {
    transform-origin: 160px 52px;
    transition: transform 0.8s cubic-bezier(0.16, 1, 0.3, 1);
}

[data-act="4"] .trunk-lid {
    transform: rotate(-35deg);
}

/* --- Screen State Transitions --- */
[data-act="1"] .screen-off    { opacity: 1; }
[data-act="1"] .screen-wake   { opacity: 0; }
[data-act="1"] .screen-listen { opacity: 0; }
[data-act="1"] .screen-result { opacity: 0; }

[data-act="2"] .screen-off    { opacity: 0; }
[data-act="2"] .screen-wake   { opacity: 1; }
[data-act="2"] .screen-listen { opacity: 0; }
[data-act="2"] .screen-result { opacity: 0; }

[data-act="3"] .screen-off    { opacity: 0; }
[data-act="3"] .screen-wake   { opacity: 0; }
[data-act="3"] .screen-listen { opacity: 1; }
[data-act="3"] .screen-result { opacity: 0; }

[data-act="4"] .screen-off    { opacity: 0; }
[data-act="4"] .screen-wake   { opacity: 0; }
[data-act="4"] .screen-listen { opacity: 0; }
[data-act="4"] .screen-result { opacity: 1; }

/* --- Scroll Indicator --- */
.scroll-indicator {
    position: absolute;
    bottom: 28px;
    left: 50%;
    transform: translateX(-50%);
    opacity: 0;
    transition: opacity 0.4s ease;
    animation: scroll-bob 2s ease-in-out infinite;
}

[data-act="1"] .scroll-indicator { opacity: 1; }

@keyframes scroll-bob {
    0%, 100% { transform: translateX(-50%) translateY(0); }
    50% { transform: translateX(-50%) translateY(6px); }
}
```

**Step 2: Verify**

Load homepage. Scroll through — the iPhone screen should transition between off → wake → listening → result. Text captions should fade in/out per act. The action arrow should pulse in Act 1 and disappear after. The Tesla trunk SVG should animate open in Act 4.

**Step 3: Commit**

```bash
git add assets/styles/homepage.css
git commit -m "feat: add parallax scroll transitions and act styling for homepage"
```

---

### Task 5: Homepage CSS — Ecosystem Section, Responsive, Polish

**Files:**
- Modify: `assets/styles/homepage.css`

**Step 1: Add ecosystem section and responsive CSS**

Append to `assets/styles/homepage.css`:

```css
/* --- Ecosystem Section (Act 5) --- */
.ecosystem-section {
    position: relative;
    padding: 8rem 2rem 6rem;
    text-align: center;
    background: #000;
}

.ecosystem-inner {
    max-width: 1000px;
    margin: 0 auto;
}

.ecosystem-title {
    font-size: clamp(2rem, 4vw, 3.25rem);
    font-weight: 700;
    letter-spacing: -0.035em;
    line-height: 1.1;
    margin-bottom: 0.5rem;
    background: linear-gradient(to bottom, #fff, rgba(255, 255, 255, 0.65));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.ecosystem-subtitle {
    font-size: 1.25rem;
    font-weight: 300;
    color: rgba(255, 255, 255, 0.35);
    margin-bottom: 4rem;
}

.skill-showcase {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 1rem;
    margin-bottom: 4rem;
}

.showcase-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 20px;
    padding: 2rem 1.25rem 1.75rem;
    transition: border-color 0.3s ease, background 0.3s ease, transform 0.3s ease;
}

.showcase-card:hover {
    border-color: rgba(255, 255, 255, 0.12);
    background: rgba(255, 255, 255, 0.05);
    transform: translateY(-2px);
}

.showcase-icon {
    width: 48px;
    height: 48px;
    margin: 0 auto 1rem;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.showcase-icon--tesla   { background: rgba(232, 33, 39, 0.12); color: #e82127; }
.showcase-icon--calendar { background: rgba(255, 59, 48, 0.12); color: #ff3b30; }
.showcase-icon--ticktick { background: rgba(78, 128, 238, 0.12); color: #4e80ee; }
.showcase-icon--hue      { background: rgba(255, 183, 0, 0.12); color: #ffb700; }
.showcase-icon--teams    { background: rgba(98, 100, 167, 0.12); color: #6264a7; }

.showcase-card h3 {
    font-size: 0.9375rem;
    font-weight: 600;
    color: #f5f5f7;
    margin-bottom: 0.375rem;
}

.showcase-command {
    font-size: 0.8125rem;
    font-weight: 300;
    color: rgba(255, 255, 255, 0.35);
    font-style: italic;
    line-height: 1.4;
}

.browse-link {
    display: inline-block;
    font-size: 1.0625rem;
    color: #2997ff;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s ease;
}

.browse-link:hover {
    color: #6cb4ff;
    text-decoration: none;
}

/* --- Responsive --- */
@media (max-width: 900px) {
    .skill-showcase {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 640px) {
    .iphone {
        transform: scale(0.82);
    }

    .act-1-text { bottom: 8%; }
    .act-2-text, .act-3-text { bottom: 10%; }
    .act-4-text { bottom: 4%; }

    .skill-showcase {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
    }

    .showcase-card {
        padding: 1.5rem 1rem;
    }

    .ecosystem-section {
        padding: 5rem 1.25rem 4rem;
    }

    .tesla-trunk {
        width: 240px;
    }

    .action-arrow {
        display: none;
    }
}
```

**Step 2: Verify**

Test at various viewport widths (mobile, tablet, desktop). Check that:
- Skill cards reflow to 3 columns on tablet, 2 on mobile
- iPhone scales down on mobile
- Text remains readable at all sizes
- Nav stays transparent and readable
- Scroll indicator works on Act 1

**Step 3: Commit**

```bash
git add assets/styles/homepage.css
git commit -m "feat: add ecosystem section and responsive styles for homepage"
```

---

### Task 6: Visual Polish Pass

**Files:**
- Possibly tweak: `assets/styles/homepage.css`
- Possibly tweak: `templates/public/home.html.twig`
- Possibly tweak: `assets/controllers/parallax_controller.js`

**Step 1: Review and refine**

Load the homepage and scroll through the entire experience. Check for:

- **Timing**: Do act transitions feel right? Adjust `transition` durations if needed.
- **Scroll feel**: Does each act get enough scroll space? Adjust `parallax-track` height if needed (try 450vh or 500vh).
- **iPhone fidelity**: Does the mockup look convincing? Adjust shadows, border-radius, button positions.
- **Screen animations**: Do the listening rings look good? Adjust sizes, timing, opacity.
- **Tesla trunk**: Does the rotation look like a trunk opening? Adjust angle and transform-origin.
- **Text positioning**: Do captions feel well-placed relative to the phone? Adjust `bottom` percentages.
- **Ecosystem cards**: Do they look balanced? Adjust padding, gaps, icon sizes.
- **Mobile**: Test on a real device or responsive mode. Hide elements that don't work well small.

**Step 2: Final commit**

```bash
git add -A
git commit -m "feat: complete homepage parallax redesign with Apple-style showcase"
```

<!DOCTYPE html>
<html lang="en">

<head>
       <meta charset="UTF-8">
       <meta name="viewport" content="width=device-width,initial-scale=1">
       <title>Kermit's · Time-honored recipes since 2000</title>
       <meta name="description" content="Enjoy time-honored recipes at Kermit's. Explore our menu and reserve a table, food order, or exclusive gathering.">
       <link rel="icon" type="image/jpeg" href="{{ asset('kermits-logo.jpg') }}">
       <style>
              * {
                     box-sizing: border-box
              }

              html {
                     scroll-behavior: smooth
              }

              body {
                     margin: 0;
                     background: #f4f5ee;
                     color: #171817;
                     font-family: Inter, ui-sans-serif, system-ui, sans-serif
              }

              a {
                     color: inherit
              }

              .site-nav {
                     height: 84px;
                     display: flex;
                     align-items: center;
                     justify-content: space-between;
                     width: min(1180px, calc(100% - 40px));
                     margin: auto
              }

              .site-brand {
                     display: flex;
                     align-items: center;
                     gap: 11px;
                     text-decoration: none
              }

              .site-brand img {
                     width: 54px;
                     height: 54px;
                     border-radius: 50%;
                     object-fit: contain;
                     background: #fff;
                     border: 1px solid #dde0d6;
                     padding: 4px
              }

              .site-brand strong {
                     letter-spacing: .12em
              }

              .nav-links {
                     display: flex;
                     align-items: center;
                     gap: 28px
              }

              .nav-links a {
                     text-decoration: none;
                     font-size: 14px
              }

              .nav-links .staff {
                     border: 1px solid #cfd3c7;
                     border-radius: 10px;
                     padding: 10px 14px
              }

              .book-button {
                     background: #171817 !important;
                     color: #fff !important;
                     border-radius: 10px;
                     padding: 11px 17px;
                     text-decoration: none !important;
                     font-weight: 700
              }

              .hero {
                     width: min(1400px, calc(100% - 24px));
                     min-height: 680px;
                     margin: 0 auto;
                     border-radius: 28px;
                     overflow: hidden;
                     background: #171817;
                     color: #fff;
                     display: grid;
                     grid-template-columns: 1.05fr .95fr;
                     position: relative
              }

              .hero-copy {
                     padding: clamp(48px, 8vw, 110px);
                     display: flex;
                     flex-direction: column;
                     justify-content: center
              }

              .eyebrow {
                     font-size: 12px;
                     letter-spacing: .18em;
                     color: #b4bf1a;
                     margin: 0 0 18px
              }

              .hero h1 {
                     font-family: Georgia, serif;
                     font-size: clamp(48px, 6vw, 82px);
                     font-weight: 500;
                     line-height: .98;
                     letter-spacing: -.04em;
                     margin: 0 0 25px
              }

              .hero-text {
                     font-size: 18px;
                     line-height: 1.7;
                     color: #c4c7c0;
                     max-width: 560px;
                     margin: 0 0 32px
              }

              .hero-actions {
                     display: flex;
                     gap: 12px;
                     flex-wrap: wrap
              }

              .hero-actions a {
                     padding: 14px 20px;
                     border-radius: 11px;
                     text-decoration: none;
                     font-weight: 700
              }

              .hero-actions .primary {
                     background: #b5c019;
                     color: #171817
              }

              .hero-actions .secondary {
                     border: 1px solid #4a4c47;
                     color: #fff
              }

              .hero-visual {
                     position: relative;
                     background: radial-gradient(circle at 40% 35%, #4c5140 0, #292b27 48%, #171817 80%);
                     display: grid;
                     place-items: center;
                     overflow: hidden
              }

              .plate {
                     width: min(430px, 75%);
                     aspect-ratio: 1;
                     border-radius: 50%;
                     background: #f7f7f2;
                     border: 28px solid #dedfd7;
                     box-shadow: 0 35px 70px #0008;
                     display: grid;
                     place-items: center;
                     transform: rotate(-8deg)
              }

              .plate img {
                     width: 82%;
                     height: 82%;
                     border-radius: 50%;
                     object-fit: cover
              }

              .plate-placeholder {
                     width: 74%;
                     height: 74%;
                     border-radius: 50%;
                     background: conic-gradient(#a8b014, #69720a, #d2a453, #7e3c1f, #a8b014);
                     display: grid;
                     place-items: center;
                     font-family: Georgia, serif;
                     font-size: 70px;
                     color: white
              }

              .since {
                     position: absolute;
                     right: 28px;
                     bottom: 28px;
                     background: #f4f5ee;
                     color: #171817;
                     border-radius: 50%;
                     width: 118px;
                     height: 118px;
                     display: grid;
                     place-items: center;
                     text-align: center;
                     font-size: 12px;
                     line-height: 1.4;
                     transform: rotate(8deg)
              }

              .section {
                     width: min(1180px, calc(100% - 40px));
                     margin: 110px auto
              }

              .section-head {
                     display: flex;
                     justify-content: space-between;
                     align-items: end;
                     margin-bottom: 34px
              }

              .section-head p {
                     color: #73776f;
                     margin: 0 0 6px;
                     font-size: 13px;
                     letter-spacing: .13em
              }

              .section-head h2 {
                     font-family: Georgia, serif;
                     font-size: clamp(36px, 4vw, 54px);
                     font-weight: 500;
                     margin: 0
              }

              .section-head>a {
                     color: #5f6500;
                     font-weight: 700
              }

              .product-grid {
                     display: grid;
                     grid-template-columns: repeat(3, 1fr);
                     gap: 18px
              }

              .menu-card {
                     background: #fff;
                     border: 1px solid #daddd1;
                     border-radius: 18px;
                     overflow: hidden
              }

              .menu-card img,
              .menu-image {
                     width: 100%;
                     height: 230px;
                     object-fit: cover
              }

              .menu-image {
                     background: #e9ecd4;
                     display: grid;
                     place-items: center;
                     color: #7c8500;
                     font-family: Georgia, serif;
                     font-size: 64px
              }

              .menu-copy {
                     padding: 21px
              }

              .menu-copy h3 {
                     margin: 0 0 8px;
                     font-size: 19px
              }

              .menu-copy p {
                     color: #73776f;
                     line-height: 1.5;
                     height: 44px;
                     overflow: hidden;
                     margin: 0 0 16px
              }

              .menu-copy strong {
                     font-size: 20px
              }

              .booking-section {
                     width: min(1400px, calc(100% - 24px));
                     margin: 110px auto;
                     background: #dfe4a0;
                     border-radius: 28px;
                     padding: clamp(36px, 6vw, 80px)
              }

              .booking-section>div:first-child {
                     max-width: 680px
              }

              .booking-section h2 {
                     font-family: Georgia, serif;
                     font-size: clamp(40px, 5vw, 64px);
                     font-weight: 500;
                     line-height: 1.05;
                     margin: 10px 0 20px
              }

              .booking-section>div>p {
                     line-height: 1.7;
                     color: #4f5348
              }

              .booking-options {
                     display: grid;
                     grid-template-columns: repeat(3, 1fr);
                     gap: 14px;
                     margin: 42px 0
              }

              .booking-option {
                     background: #f8f9f3;
                     border-radius: 16px;
                     padding: 25px
              }

              .booking-option span {
                     width: 42px;
                     height: 42px;
                     border-radius: 50%;
                     display: grid;
                     place-items: center;
                     background: #171817;
                     color: white;
                     font-size: 20px
              }

              .booking-option h3 {
                     margin: 18px 0 8px
              }

              .booking-option p {
                     color: #73776f;
                     line-height: 1.55;
                     margin: 0
              }

              .booking-section .book-button {
                     display: inline-block;
                     padding: 14px 21px
              }

              .story {
                     display: grid;
                     grid-template-columns: 1fr 1fr;
                     gap: 70px;
                     align-items: center
              }

              .story-mark {
                     aspect-ratio: 1;
                     border-radius: 50%;
                     background: #171817;
                     display: grid;
                     place-items: center;
                     padding: 18%
              }

              .story-mark img {
                     width: 100%;
                     border-radius: 50%;
                     background: white
              }

              .story h2 {
                     font-family: Georgia, serif;
                     font-size: clamp(40px, 5vw, 62px);
                     font-weight: 500;
                     line-height: 1.05;
                     margin: 10px 0 22px
              }

              .story p {
                     color: #676c63;
                     line-height: 1.8
              }

              .footer {
                     background: #171817;
                     color: white;
                     padding: 55px 20px
              }

              .footer-inner {
                     width: min(1180px, 100%);
                     margin: auto;
                     display: flex;
                     justify-content: space-between;
                     gap: 30px;
                     align-items: center
              }

              .footer-brand {
                     display: flex;
                     align-items: center;
                     gap: 12px
              }

              .footer-brand img {
                     width: 58px;
                     height: 58px;
                     border-radius: 50%;
                     background: white
              }

              .footer small {
                     color: #8f928c
              }

              .menu-toggle {
                     display: none;
                     border: 0;
                     background: none;
                     font-size: 25px
              }

              @media(max-width:900px) {
                     .nav-links a:not(.book-button) {
                            display: none
                     }

                     .menu-toggle {
                            display: none
                     }

                     .hero {
                            grid-template-columns: 1fr;
                            min-height: auto
                     }

                     .hero-copy {
                            min-height: 560px
                     }

                     .hero-visual {
                            min-height: 500px
                     }

                     .product-grid {
                            grid-template-columns: repeat(2, 1fr)
                     }

                     .story {
                            gap: 35px
                     }

                     .booking-options {
                            grid-template-columns: 1fr
                     }

                     .section {
                            margin-block: 75px
                     }
              }

              @media(max-width:600px) {
                     .site-nav {
                            width: calc(100% - 24px);
                            height: 72px
                     }

                     .site-brand strong {
                            font-size: 13px
                     }

                     .site-brand img {
                            width: 44px;
                            height: 44px
                     }

                     .nav-links {
                            gap: 8px
                     }

                     .nav-links .staff {
                            display: none
                     }

                     .book-button {
                            font-size: 13px;
                            padding: 10px 12px
                     }

                     .hero {
                            width: 100%;
                            border-radius: 0
                     }

                     .hero-copy {
                            padding: 55px 22px;
                            min-height: 500px
                     }

                     .hero h1 {
                            font-size: 49px
                     }

                     .hero-text {
                            font-size: 16px
                     }

                     .hero-visual {
                            min-height: 380px
                     }

                     .since {
                            width: 90px;
                            height: 90px
                     }

                     .section {
                            width: calc(100% - 28px);
                            margin-block: 65px
                     }

                     .section-head {
                            align-items: start;
                            gap: 12px
                     }

                     .section-head>a {
                            display: none
                     }

                     .product-grid {
                            grid-template-columns: 1fr
                     }

                     .menu-card img,
                     .menu-image {
                            height: 250px
                     }

                     .booking-section {
                            width: 100%;
                            border-radius: 0;
                            margin-block: 65px;
                            padding: 55px 20px
                     }

                     .story {
                            grid-template-columns: 1fr
                     }

                     .story-mark {
                            max-width: 340px;
                            margin: auto
                     }

                     .footer-inner {
                            display: grid;
                            text-align: center;
                            justify-items: center
                     }
              }

              html,
              body {
                     scrollbar-width: none;
                     -ms-overflow-style: none
              }

              html::-webkit-scrollbar,
              body::-webkit-scrollbar {
                     display: none;
                     width: 0;
                     height: 0
              }

              :root {
                     --ink: #171817;
                     --paper: #f5f3eb;
                     --surface: #fffefa;
                     --line: #d8d9cf;
                     --accent: #b5c019;
                     --muted: #696e65
              }

              body {
                     background: radial-gradient(circle at 90% 5%, #eef0d0 0, transparent 24%), var(--paper)
              }

              .site-nav {
                     position: sticky;
                     top: 0;
                     z-index: 50;
                     height: 76px;
                     width: 100%;
                     padding: 0 max(18px, calc((100% - 1180px)/2));
                     background: rgba(245, 243, 235, .92);
                     border-bottom: 1px solid rgba(216, 217, 207, .8);
                     backdrop-filter: blur(14px)
              }

              .nav-links a {
                     font-weight: 650
              }

              .nav-links .book-button {
                     box-shadow: 0 8px 22px rgba(0, 0, 0, .15)
              }

              .hero {
                     min-height: calc(100dvh - 100px);
                     max-height: 860px;
                     background: linear-gradient(135deg, #151615, #23251f);
                     box-shadow: 0 28px 70px rgba(20, 22, 18, .18)
              }

              .hero-copy {
                     padding-inline: clamp(38px, 7vw, 100px)
              }

              .hero-actions a {
                     min-height: 48px;
                     display: inline-flex;
                     align-items: center;
                     justify-content: center;
                     transition: .2s
              }

              .hero-actions .primary:hover {
                     background: #c7d22b;
                     transform: translateY(-2px)
              }

              .hero-actions .secondary:hover {
                     background: #fff;
                     color: #171817
              }

              .section {
                     margin-block: 90px
              }

              .section-head>a {
                     padding: 10px 0;
                     text-underline-offset: 5px
              }

              .menu-card {
                     background: var(--surface);
                     border-color: var(--line);
                     box-shadow: 0 14px 38px rgba(28, 30, 25, .08);
                     transition: transform .2s, box-shadow .2s
              }

              .menu-card:hover {
                     transform: translateY(-5px);
                     box-shadow: 0 22px 50px rgba(28, 30, 25, .13)
              }

              .menu-category-label {
                     display: inline-block;
                     margin-bottom: 8px;
                     color: #737c00;
                     font-size: 11px;
                     font-weight: 850;
                     text-transform: uppercase;
                     letter-spacing: .09em
              }

              .menu-copy {
                     display: flex;
                     flex-direction: column;
                     min-height: 170px
              }

              .menu-copy strong {
                     margin-top: auto
              }

              .booking-section {
                     position: relative;
                     overflow: hidden;
                     background: linear-gradient(135deg, #dce28f, #cbd472);
                     box-shadow: 0 24px 65px rgba(78, 86, 20, .15)
              }

              .booking-section:after {
                     content: "K";
                     position: absolute;
                     right: -30px;
                     top: -90px;
                     font: 280px Georgia, serif;
                     color: rgba(255, 255, 255, .18);
                     pointer-events: none
              }

              .booking-options,
              .booking-section>.book-button {
                     position: relative;
                     z-index: 1
              }

              .booking-option {
                     border: 1px solid rgba(255, 255, 255, .65);
                     box-shadow: 0 12px 30px rgba(71, 76, 26, .08)
              }

              .book-button {
                     display: inline-flex !important;
                     align-items: center;
                     justify-content: center;
                     min-height: 48px;
                     border-radius: 12px !important
              }

              .landing-category {
                     grid-column: 1/-1
              }

              @media(max-width:900px) {
                     .hero {
                            min-height: auto;
                            max-height: none
                     }

                     .hero-copy {
                            min-height: 500px
                     }

                     .section {
                            margin-block: 70px
                     }
              }

              @media(max-width:600px) {
                     .site-nav {
                            height: 68px;
                            padding: 0 12px
                     }

                     .nav-links .book-button {
                            display: inline-flex !important
                     }

                     .hero-copy {
                            min-height: 450px;
                            padding: 46px 20px
                     }

                     .hero-actions {
                            display: grid;
                            grid-template-columns: 1fr 1fr
                     }

                     .hero-actions a {
                            padding: 12px !important;
                            text-align: center
                     }

                     .hero-visual {
                            min-height: 330px
                     }

                     .section {
                            margin-block: 54px
                     }

                     .section-head {
                            display: block
                     }

                     .section-head>a {
                            display: inline-block;
                            margin-top: 12px
                     }

                     .menu-card img,
                     .menu-image {
                            height: 220px
                     }

                     .booking-section {
                            margin-block: 54px
                     }

                     .booking-section>.book-button {
                            width: 100%
                     }

                     .story .book-button {
                            width: 100%;
                            margin-top: 8px
                     }
              }

              /* Edge-to-edge public experience */
              .hero {
                     width: 100%;
                     min-height: calc(100dvh - 76px);
                     border-radius: 0;
                     box-shadow: none
              }

              .booking-section {
                     width: 100%;
                     border-radius: 0
              }

              .site-nav {
                     max-width: none
              }

              .footer {
                     margin: 0
              }

              .section {
                     width: min(1240px, calc(100% - 40px))
              }

              @media(max-width:600px) {
                     .hero {
                            min-height: auto
                     }

                     .booking-section {
                            width: 100%
                     }

                     .section {
                            width: calc(100% - 24px)
                     }
              }

              button,
              a,
              .book-button,
              .hero-actions a,
              .menu-card {
                     transform: none !important;
                     transition: background-color .14s ease, border-color .14s ease, color .14s ease, box-shadow .14s ease, opacity .14s ease !important
              }

              button:hover,
              a:hover,
              .book-button:hover,
              .hero-actions a:hover,
              .menu-card:hover,
              button:active,
              a:active {
                     transform: none !important;
                     filter: none !important
              }

              @media(prefers-reduced-motion:reduce) {

                     *,
                     *:before,
                     *:after {
                            scroll-behavior: auto !important;
                            animation: none !important;
                            transition: none !important
                     }
              }

              .app-download {
                     min-height: 40px;
                     display: inline-flex;
                     align-items: center;
                     padding: 9px 13px;
                     border: 1px solid #cfd3c7;
                     border-radius: 10px;
                     font-size: 14px;
                     font-weight: 700;
                     white-space: nowrap
              }

              .app-download.disabled {
                     color: #777b72;
                     background: #eceee6;
                     cursor: default
              }

              @media(max-width:900px) {
                     .app-download {
                            display: none
                     }
              }

              .hero-actions .app-hero-action {
                     min-height: 48px;
                     display: inline-flex;
                     align-items: center;
                     justify-content: center;
                     padding: 14px 20px;
                     border: 1px solid #4a4c47;
                     border-radius: 11px;
                     color: #fff;
                     font-weight: 700
              }

              .hero-actions .app-hero-action.disabled {
                     opacity: .58;
                     cursor: default
              }

              @media(max-width:600px) {
                     .hero-actions .app-hero-action {
                            grid-column: 1/-1
                     }
              }
       </style>
</head>

<body>
       <nav class="site-nav"><a class="site-brand" href="{{ route('home') }}"><img src="{{ asset('kermits-logo.jpg') }}" alt="Kermit's"><strong>KERMIT'S</strong></a>
              <div class="nav-links"><a href="#menu">Menu</a><a href="#story">Our story</a>@if($appDownloadAvailable)<a class="app-download" href="{{ $appDownloadUrl }}" download>Download app</a>@else<span class="app-download disabled" title="The Android app will be available soon">App coming soon</span>@endif<a class="staff" href="{{ route('login') }}">Log in</a><a class="book-button" href="{{ route('shop') }}">Order now</a></div>
       </nav>
       <main>
              <section class="hero">
                     <div class="hero-copy">
                            <p class="eyebrow">TIME-HONORED RECIPES SINCE 2000</p>
                            <h1>Good food.<br>Good company.</h1>
                            <p class="hero-text">Come together over familiar favorites, prepared with care and served with the warm hospitality Kermit’s is known for.</p>
                            <div class="hero-actions"><a class="primary" href="{{ route('reservations.create') }}">Reserve your visit</a><a class="secondary" href="#menu">Explore the menu</a>@if($appDownloadAvailable)<a class="secondary app-hero-action" href="{{ $appDownloadUrl }}" download>Download Android app</a>@else<span class="secondary app-hero-action disabled">Android app coming soon</span>@endif</div>
                     </div>
                     <div class="hero-visual">
                            <div class="plate">@if($heroImageUrl = $products->first()?->imageUrl())<img src="{{ $heroImageUrl }}" alt="{{ $products->first()->name }}">@else<div class="plate-placeholder">K</div>@endif</div>
                            <div class="since">SERVING<br><strong>SINCE<br>2000</strong></div>
                     </div>
              </section>
              <section class="section" id="menu">
                     <div class="section-head">
                            <div>
                                   <p>MOST LOVED AT KERMIT'S</p>
                                   <h2>Best sellers</h2>
                            </div><a href="{{ route('login') }}">Log in to view the full menu &rarr;</a>
                     </div>
                     <div class="product-grid">@forelse($products as $product)<article class="menu-card">@if($imageUrl = $product->imageUrl())<img src="{{ $imageUrl }}" alt="{{ $product->name }}">@else<div class="menu-image">{{ strtoupper(substr($product->name,0,1)) }}</div>@endif<div class="menu-copy"><span class="menu-category-label">{{ $product->category }}</span>
                                          <h3>{{ $product->name }}</h3>
                                          <p>{{ $product->description ?: 'Prepared fresh for every guest.' }}</p><strong>&#8369;{{ number_format($product->price,2) }}</strong>
                                   </div>
                            </article>@empty<p>No best sellers are available yet.</p>@endforelse</div>
              </section>
              <section class="booking-section">
                     <div>
                            <p class="eyebrow" style="color:#626900">MAKE IT YOURS</p>
                            <h2>A table, a feast, or the whole place.</h2>
                            <p>Whether it’s a quiet meal or a milestone celebration, tell us what you have in mind and we’ll help you plan it.</p>
                     </div>
                     <div class="booking-options">
                            <article class="booking-option"><span>&#127869;</span>
                                   <h3>Table booking</h3>
                                   <p>Reserve the right table for your group and preferred schedule.</p>
                            </article>
                            <article class="booking-option"><span>&#127828;</span>
                                   <h3>Food Request</h3>
                                   <p>Choose menu items you would like prepared for your reservation.</p>
                            </article>
                            <article class="booking-option"><span>&#10024;</span>
                                   <h3>Exclusive reservation</h3>
                                   <p>Plan private celebrations and full-venue gatherings.</p>
                            </article>
                     </div><a class="book-button" href="{{ route('reservations.create') }}">Start your reservation</a>
              </section>
              <section class="section story" id="story">
                     <div class="story-mark"><img src="{{ asset('kermits-logo.jpg') }}" alt="Kermit's time-honored recipes since 2000"></div>
                     <div>
                            <p class="eyebrow" style="color:#747d00">OUR STORY</p>
                            <h2>Familiar flavors, shared since 2000.</h2>
                            <p>Kermit’s brings people together around recipes that feel like home. From everyday meals to special celebrations, we focus on food made with care and service that makes every guest feel welcome.</p><a class="book-button" href="{{ route('reservations.create') }}">Plan your visit</a>
                     </div>
              </section>
       </main>
       <footer class="footer">
              <div class="footer-inner">
                     <div class="footer-brand"><img src="{{ asset('kermits-logo.jpg') }}" alt=""><strong>KERMIT'S</strong></div><small>Time-honored recipes since 2000</small>
              </div>
       </footer>



</body>

</html>

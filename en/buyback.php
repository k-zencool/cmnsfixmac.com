<?php
// This page doesn't seem to require PHP logic at the top,
// so we can start directly with the HTML.
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description"
        content="We buy used MacBooks, iPhones, iPads, and iMacs of all models and conditions in Chiang Mai! Get a fair price, free evaluation, and local pickup. Get a quote easily via LINE." />
    <title>CMNS Mac: We Buy Used MacBooks, iPhones, iPads, iMacs in Chiang Mai | Fair Price</title>

    <link rel="alternate" hreflang="th" href="https://cmnsfixmac.com/buyback.php" />
    <link rel="alternate" hreflang="en" href="https://cmnsfixmac.com/en/buyback.php" />
    <link rel="alternate" hreflang="x-default" href="https://cmnsfixmac.com/en/buyback.php" />


    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/buyback-style.css">
    <link rel="stylesheet" href="/assets/css/footer-style.css">
    <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png" />

    

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.3/dist/css/splide.min.css">
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

    <script async src="https://www.googletagmanager.com/gtag/js?id=G-3WXK9GWN7C"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }
        gtag('js', new Date());
        gtag('config', 'G-3WXK9GWN7C');
    </script>

    <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "FAQPage",
            "mainEntity": [{
                    "@type": "Question",
                    "name": "My device is severely damaged and won't turn on. Do you really buy it?",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "Yes! We buy devices in any condition, as stated. Even if it doesn't turn on, we can value it for its parts. Feel free to send it for a no-obligation quote."
                    }
                },
                {
                    "@type": "Question",
                    "name": "Do you buy iCloud-locked devices or other locked devices?",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "We will consider it. The price will depend on the model, condition, and the type of lock. Please provide full details when you send it for an evaluation."
                    }
                },
                {
                    "@type": "Question",
                    "name": "I live outside of Chiang Mai. Can I send my device to sell?",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "Yes. If you cannot come to Chiang Mai, you can securely pack and ship your device to us. After we receive and inspect the device and agree on the price, we will transfer the money to you immediately. (We recommend asking us for detailed shipping instructions first)."
                    }
                },
                {
                    "@type": "Question",
                    "name": "How long does it take to get a price and receive the money?",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "The initial price evaluation via LINE is very fast, usually within a few hours (during business hours). If you agree to sell and we meet up or receive the device, the final inspection and payment are also very quick."
                    }
                },
                {
                    "@type": "Question",
                    "name": "What do I need to prepare when selling my device?",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "Mainly the device itself. If you have the original box and all genuine accessories (charging cable, adapter), it will help you get a better price. Most importantly, please remember to sign out of your Apple ID and iCloud first."
                    }
                }
            ]
        }
    </script>
</head>

<body>
    <?php include_once '../includes/header_en.php'; // Path changed 
    ?>

    <header class="hero-buyback" id="top">
        <div class="container">
            <h1>We Buy Used MacBooks, iPhones, iPads, & iMacs in Chiang Mai</h1>
            <p>Broken device, won't turn on, cracked screen, bad battery? We take it all! Fair prices, fast payment, and local pickup available.</p>
            <a href="https://page.line.me/cmns" target="_blank" class="btn-line">
                <span class="material-symbols-rounded">chat</span> Get a Free Quote via LINE Now!
            </a>
            <p style="font-size: 0.9em; margin-top: 15px;">(Free estimate! No obligation to sell.)</p>
        </div>
    </header>

    <nav class="nav-top">
        <div class="container">
            <a href="#services">Models We Buy</a>
            <a href="#why-us">Why Sell to Us?</a>
            <a href="#steps">How to Sell</a>
            <a href="#gallery">Buyback Examples</a>
            <a href="#faq">FAQ</a>
            <a href="#contact">Contact / Map</a>
        </div>
    </nav>

    <section id="why-us" class="about" data-aos="fade-up">
        <div class="container">
            <h2><span class="material-symbols-rounded">verified_user</span> Why Should You Sell Your Apple Device to CMNS Mac?</h2>
            <ul>
                <li><span class="material-symbols-rounded">thumb_up</span>
                    <strong>The Fairest Prices in Chiang Mai:</strong> We evaluate based on the actual condition, no lowballing.
                </li>
                <li><span class="material-symbols-rounded">flash_on</span> <strong>Fast Evaluation:</strong>
                    Get a preliminary quote quickly via LINE.</li>
                <li><span class="material-symbols-rounded">handshake</span> <strong>We Buy Any Condition:</strong>
                    Pristine, minor flaws, cracked screen, won't turn on, iCloud locked - we consider them all.</li>
                <li><span class="material-symbols-rounded">local_shipping</span> <strong>Convenient:</strong>
                    Local pickup service in Chiang Mai city and nearby areas, or you can ship it to us.</li>
                <li><span class="material-symbols-rounded">paid</span> <strong>Instant Payment:</strong> Once the price is agreed upon, get paid in cash or via bank transfer immediately. No waiting.</li>
                <li><span class="material-symbols-rounded">storefront</span> <strong>A Local Chiang Mai Shop
                        for Local People:</strong> Friendly and sincere service.</li>
            </ul>
        </div>
    </section>

    <section id="services" class="device-section" data-aos="fade-up">
        <div class="container">
            <h2><span class="material-symbols-rounded">devices</span> Which Apple Devices Do We Buy? (Any Condition!)
            </h2>
            <div class="device-item" id="macbook">
                <h3><span class="material-symbols-rounded">laptop_mac</span> All MacBook Models</h3>
                <p>We buy MacBook Air, MacBook Pro (all screen sizes, Intel, M1, M2, M3, M4 chips) from 2015 onwards. Any condition is accepted, just send it for a quote!</p>
                <a href="/assets/img/buyback/Macbook.webp" data-lightbox="macbook-gallery"
                    data-title="We buy all MacBook models in any condition.">
                    <img src="/assets/img/buyback/Macbook.webp" alt="We buy used MacBooks in Chiang Mai"> </a>
            </div>

            <div class="device-item" id="iphone">
                <h3><span class="material-symbols-rounded">smartphone</span> All iPhone Models</h3>
                <p>We buy iPhones from iPhone 11, 12, 13, 14, 15, 16 (all Pro, Pro Max, Mini, Plus models). You can also inquire about older models. Good condition, broken, cracked screen, locked - all are considered.</p>
                <a href="/assets/img/buyback/iPhone.webp" data-lightbox="iphone-gallery"
                    data-title="We buy all iPhone models in any condition.">
                    <img src="/assets/img/buyback/iPhone.webp" alt="We buy used iPhones in Chiang Mai"> </a>
            </div>

            <div class="device-item" id="ipad">
                <h3><span class="material-symbols-rounded">tablet_mac</span> All iPad Models</h3>
                <p>We buy iPad, iPad Air, iPad Pro, iPad mini, all generations from 2015 onwards. Good condition, dents, screen issues, bad battery - send us your offer.</p>
                <a href="/assets/img/buyback/iPad.webp" data-lightbox="ipad-gallery"
                    data-title="We buy all iPad models in any condition.">
                    <img src="/assets/img/buyback/iPad.webp" alt="We buy used iPads in Chiang Mai"> </a>
            </div>

            <div class="device-item" id="imac">
                <h3><span class="material-symbols-rounded">desktop_mac</span> All iMac Models</h3>
                <p>We buy iMacs (21.5", 24", 27", Intel, M1, M3 chips) from 2015 onwards. We buy them whether they're in perfect condition or have issues and won't turn on.</p>
                <a href="/assets/img/buyback/iMac.webp" data-lightbox="imac-gallery"
                    data-title="We buy all iMac models in any condition.">
                    <img src="/assets/img/buyback/iMac.webp" alt="We buy used iMacs in Chiang Mai"> </a>
            </div>
            <p style="text-align: center; margin-top: 30px; font-weight: bold;">We also consider other devices like Apple Watch and Accessories. Just ask!</p>
        </div>
    </section>

    <section id="gallery" class="gallery" data-aos="fade-up">
        <div class="container">
            <h2><span class="material-symbols-rounded">photo_library</span> Examples of Devices We've Bought (We Mean Any Condition!)</h2>
            <div class="splide">
                <div class="splide__track">
                    <ul class="splide__list">
                        <li class="splide__slide">
                            <img src="/assets/img/buyback/sample-mbp-good.webp" alt="MacBook Pro in good condition">
                            <figcaption class="splide-caption">MacBook Pro 2020 (Mint Condition)</figcaption>
                        </li>
                        <li class="splide__slide">
                            <img src="/assets/img/buyback/sample-iphone-broken.webp" alt="iPhone with a cracked screen">
                            <figcaption class="splide-caption">iPhone 12 (Cracked screen, but still accepted)</figcaption>
                        </li>
                        <li class="splide__slide">
                            <img src="/assets/img/buyback/sample-imac-ok.webp" alt="iMac with normal wear and tear">
                            <figcaption class="splide-caption">iMac 2019 (Some scratches, but works fine)</figcaption>
                        </li>
                        <li class="splide__slide">
                            <img src="/assets/img/buyback/sample-ipad-bad.webp" alt="iPad that won't turn on">
                            <figcaption class="splide-caption">iPad Air 3 (Won't turn on, bought for parts)</figcaption>
                        </li>
                        <li class="splide__slide">
                            <img src="/assets/img/buyback/sample-macbook-icloud.webp" alt="iCloud locked MacBook">
                            <figcaption class="splide-caption">MacBook Air M1 (iCloud locked, we can consult)</figcaption>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section id="steps" class="steps" data-aos="fade-up">
        <div class="container">
            <h2><span class="material-symbols-rounded">list_alt</span> 4 Easy Steps to Sell Your Apple Device to Us</h2>
            <ol>
                <li><span class="material-symbols-rounded">photo_camera</span> <strong>Take Photos of Your Device:</strong> Clear photos from multiple angles, especially any flaws (if any).</li>
                <li><span class="material-symbols-rounded">chat_bubble</span> <strong>Send Info via LINE:</strong> Tell us the model, basic specs, and condition, along with the photos.</li>
                <li><span class="material-symbols-rounded">request_quote</span> <strong>Receive Our Quote:</strong> We will provide you with a preliminary price estimate quickly.</li>
                <li><span class="material-symbols-rounded">local_mall</span> <strong>Meet Up/Ship & Get Paid:</strong> Arrange a meeting for a physical check (in Chiang Mai) or ship the device. Once agreed, get paid instantly!</li>
            </ol>
        </div>
    </section>

    <section id="faq" class="faq" data-aos="fade-up">
        <div class="container">
            <h2><span class="material-symbols-rounded">help_outline</span> Frequently Asked Questions (FAQ)</h2>
            <h3>Q: My device is severely damaged and won't turn on. Do you really buy it?</h3>
            <p>A: Yes! We buy devices in any condition, as stated. Even if it doesn't turn on, we can value it for its parts. Feel free to send it for a no-obligation quote.</p>
            <h3>Q: Do you buy iCloud-locked devices or other locked devices?</h3>
            <p>A: We will consider it. The price will depend on the model, condition, and the type of lock. Please provide full details when you send it for an evaluation.</p>
            <h3>Q: I live outside of Chiang Mai. Can I send my device to sell?</h3>
            <p>A: Yes. If you cannot come to Chiang Mai, you can securely pack and ship your device to us. After we receive and inspect the device and agree on the price, we will transfer the money to you immediately. (We recommend asking us for detailed shipping instructions first).</p>
            <h3>Q: How long does it take to get a price and receive the money?</h3>
            <p>A: The initial price evaluation via LINE is very fast, usually within a few hours (during business hours). If you agree to sell and we meet up or receive the device, the final inspection and payment are also very quick.</p>
            <h3>Q: What do I need to prepare when selling my device?</h3>
            <p>A: Mainly the device itself. If you have the original box and all genuine accessories (charging cable, adapter), it will help you get a better price. Most importantly, please remember to sign out of your Apple ID and iCloud first.</p>
        </div>
    </section>

    <section id="counter" class="counter-section" data-aos="fade-up">
        <div class="container">
            <h2><span class="material-symbols-rounded">sell</span> We Are Proud to Be Trusted</h2>
            <p>Number of devices we have evaluated and bought is over</p>
            <h3 class="counter" data-count="1857">0</h3>
            <p>and still counting!</p>
        </div>
    </section>

    <section id="contact" class="map" data-aos="fade-up">
        <div class="container">
            <h2><span class="material-symbols-rounded">location_on</span> Contact Us / Meetup Point in Chiang Mai</h2>
            <p style="text-align:center; margin-bottom:20px;">Contact us for inquiries, device inspection appointments, or price evaluations. We're happy to help.<br>We primarily arrange meetups in Chiang Mai city or as agreed upon. (Call or LINE us to discuss first).</p>
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3497.641581228488!2d98.967748!3d18.751606100000004!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x30da3aa79be8e5db%3A0x1a948e6def350e!2z4LiL4LmI4Lit4LihIG1hYyDguYDguIrguLXguKLguIfguYPguKvguKHguYggKEZpeCBtYWMgQ2hpYW5nbWFpKQ!5e1!3m2!1sth!2sth!4v1748670162801!5m2!1sth!2sth" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy"
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>
    </section>

    <?php include_once '../includes/footer_en.php'; // Path changed 
    ?>

    <script src="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.3/dist/js/splide.min.js"></script>
    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script>
        // Main script (no text to translate here, so it's fine)
        document.addEventListener('DOMContentLoaded', function() {
            AOS.init({
                duration: 1000,
                once: true,
            });

            if (document.querySelector('.splide')) {
                new Splide('.splide', {
                    type: 'loop',
                    perPage: 3,
                    gap: '1rem',
                    autoplay: true,
                    pauseOnHover: true,
                    breakpoints: {
                        768: {
                            perPage: 2,
                        },
                        576: {
                            perPage: 1,
                        }
                    }
                }).mount();
            }

            const counters = document.querySelectorAll('.counter');
            const speed = 200;
            counters.forEach(counter => {
                const animateCount = () => {
                    const target = +counter.getAttribute('data-count');
                    const count = +counter.innerText;
                    const inc = Math.ceil(target / speed);
                    if (count < target) {
                        counter.innerText = count + inc;
                        setTimeout(animateCount, 1);
                    } else {
                        counter.innerText = target.toLocaleString();
                    }
                };
                setTimeout(animateCount, 500);
            });
        });
    </script>
</body>

</html>
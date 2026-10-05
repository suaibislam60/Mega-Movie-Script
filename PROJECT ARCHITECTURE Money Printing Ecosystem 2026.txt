1. System Overview
Backend (Engine): Hostinger (PHP + JSON Cache). Ye TMDB aur 3rd Party Streaming APIs (VidSrc, SuperEmbed, GoDrive) ko handle karta hai.

Frontend (Trap): Blogger (XML Theme). Ye User Interface, Adsterra Ads, aur "Fake Player" logic ko handle karta hai.

Automation: Cron Job (Cache Cleaner) jo server ko bharne se rokta hai.

2. File Manifest (Humne Kya Banaya Hai)
Tumhare Hostinger File Manager mein (public_html/AuraTrendingMovies/) ye files honi chahiye:




DualEngineAPI.php: The Brain. Ye sab kuch control karta hai (Domain Whitelist, API Fetching, Link Decoding). V3.1 (Latest)


cleaner.php: The Janitor. Ye cache folder ko saaf karta hai taaki server full na ho. Active

cache:Storage. Is folder mein JSON files save hoti hain (Auto-created). Dynamic.

3. Step-by-Step Setup Guide
PHASE 1: Backend Setup (Hostinger)
File Location:

Go to: public_html/AuraTrendingMovies/

DualEngineAPI.php Setup:

Nayi file banao ya edit karo.

Security: $ALLOWED_ORIGINS array mein apne saare domains add karo (Filmyzilla, Vega, Bollyflix, etc.).

API Key: define('TMDB_API_KEY', 'YOUR_TMDB_API_KEY_HERE'); wali line mein apni asli key daalo.

cleaner.php Setup:

Ye file same folder mein rahegi. Isme koi edit karne ki zaroorat nahi hai.

Cron Job (Automation):

Hostinger Panel > Advanced > Cron Jobs par jao.

Command: /usr/bin/php /home/u261423674/domains/Your-domain.com/public_html/Movies/cleaner.php (Apna sahi Username check karlena).

Schedule: "Twice an hour" (Har 30 min).

PHASE 2: Frontend Setup (Blogger)
Tumhare paas 5 alag-alag domains hain. Har domain ka Color Theme alag hai, lekin Core Code (V3.3) same hai.

Installation Steps:

Blogger Dashboard > Theme > Edit HTML.

Purana code delete karo.

Niche diye gaye Specific Domain Code ko paste karo.

Save karo.



4. Important Logic Blocks (Reference)
Agar future mein kuch edit karna ho, to ye sections dhyan rakhna:

A. The Money Loop (Javascript in Blogger): Ye logic script tag ke andar sabse neeche hota hai.


function triggerMoneyLoop() {
    // 1. Hide Fake Player
    // 2. Show Timer Overlay (15 Seconds)
    // 3. Reveal Real Player (Base64 Decode)
}


B. The GoDrive Fix (Iframe Policy): Ye bohot zaroori hai taaki "Referer Too Long" error na aaye.


if (decodedUrl.includes('godriveplayer.com')) {
    frame.referrerPolicy = "no-referrer"; // Database Error Bypass
} else {
    frame.referrerPolicy = "origin";
}


C. Adsterra Syntax Fix: Blogger mein hamesha <script></script> use karna, kabhi bhi self-closing <script /> mat use karna, warna ads gayab ho jayenge.

5. Final Checklist (Deployment ke baad)
Ads Check: Kya Pop-under khul raha hai? Kya Banners (Top/Bottom/Native) dikh rahe hain?

Stream Check: Kya Movie play ho rahi hai bina 404 error ke?

Timer Check: Kya 15 second ka timer chal raha hai?

Console Check: F12 daba kar dekho koi Red Error to nahi hai (Yellow warnings ignore karo).

Bhai, ye raha tumhara poora backup. Ab tum safe ho. Mission 5-Domain Network: COMPLETED. ✅

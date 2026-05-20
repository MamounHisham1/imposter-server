<script setup>
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import NavBar from '../Components/NavBar.vue';
import SiteFooter from '../Components/SiteFooter.vue';

const { t, locale } = useI18n();
</script>

<template>
    <Head>
        <title>About Traitor (الخائن) — Free Online Social Deduction Game</title>
        <meta name="description" content="Traitor (al-Khaina) is a free real-time multiplayer social deduction word game with a Wild West theme. Play with 3-10 friends in Arabic or English. No download required." head-key="description" />
        <meta property="og:title" content="About Traitor — Free Online Social Deduction Game" head-key="og_title" />
        <meta property="og:description" content="Discover Traitor (al-Khaina) — the free browser-based social deduction word game. Wild West theme, Arabic & English support, 3-10 players, real-time multiplayer." head-key="og_description" />
    </Head>

    <div class="min-h-screen flex flex-col items-center p-4 md:p-8">
        <NavBar />

        <div class="max-w-3xl w-full space-y-8" :dir="locale === 'ar' ? 'rtl' : 'ltr'" :class="locale === 'ar' ? 'text-right' : 'text-left'">

            <div class="text-center mb-8">
                <img :src="'/logo.png'" alt="Traitor Game Logo" class="w-32 h-32 md:w-48 md:h-48 mx-auto object-contain drop-shadow-2xl mb-4" loading="lazy" />
                <h1 class="text-4xl md:text-5xl wanted-text uppercase mb-3">
                    {{ locale === 'ar' ? 'عن الخائن' : 'About Traitor' }}
                </h1>
                <p class="text-xl text-[#d3bfa1]">
                    {{ locale === 'ar' ? 'من هو الخائن في البلدة؟' : 'Who is the traitor in town?' }}
                </p>
            </div>

            <!-- Game Description -->
            <section class="wanted-poster p-6 md:p-8">
                <h2 class="text-2xl md:text-3xl wanted-text mb-4 border-b border-dashed border-[#8b4513] pb-2">
                    {{ locale === 'ar' ? 'ما هي لعبة الخائن؟' : 'What is Traitor?' }}
                </h2>
                <p class="text-lg text-[#4a2511] leading-relaxed mb-4">
                    {{ locale === 'ar'
                        ? 'الخائن هي لعبة استنتاج اجتماعي كلمية تعمل في المتصفح مباشرة بأسلوب الغرب الأمريكي القديم. اللعبة تجمع بين الإثارة النفسية لألعاب مثل Spyfall وبين ذكاء ألعاب الكلمات مثل Codenames.'
                        : 'Traitor (al-Khaina) is a browser-based social deduction word game set in the Wild West. It combines the psychological thrill of games like Spyfall with the cleverness of word games like Codenames.'
                    }}
                </p>
                <p class="text-lg text-[#4a2511] leading-relaxed mb-4">
                    {{ locale === 'ar'
                        ? 'في كل جولة، يحصل جميع اللاعبين على كلمة سرية — باستثناء لاعب واحد وهو الخائن، الذي يتلقى تلميحاً غامضاً فقط. يجب على كل لاعب تقديم تلميح بكلمة واحدة. على العصابة أن يكتشفوا من يكذب، بينما يحاول الخائن الاندساج بينهم.'
                        : 'In each round, all players receive a secret word — except one. The Traitor gets only a vague hint. Every player must give a one-word clue. The crew must figure out who\'s lying, while the Traitor tries to blend in.'
                    }}
                </p>
                <p class="text-lg text-[#4a2511] leading-relaxed">
                    {{ locale === 'ar'
                        ? 'اللعبة فريدة من نوعها كونها اللعبة الاجتماعية الاستنتاجية الوحيدة التي تدعم اللغة العربية بشكل كامل، مع واجهة مصممة خصيصاً للاتجاه من اليمين إلى اليسار.'
                        : 'The game is unique as the only social deduction game that fully supports Arabic, with an interface specifically designed for right-to-left text direction.'
                    }}
                </p>
            </section>

            <!-- Features -->
            <section class="wanted-poster p-6 md:p-8">
                <h2 class="text-2xl md:text-3xl wanted-text mb-4 border-b border-dashed border-[#8b4513] pb-2">
                    {{ locale === 'ar' ? 'المميزات' : 'Features' }}
                </h2>
                <div class="grid md:grid-cols-2 gap-4">
                    <div class="bg-[#d3bfa1]/30 p-4 rounded">
                        <h3 class="text-lg text-[#8b2500] mb-2">{{ locale === 'ar' ? 'لعب فوري' : 'Instant Play' }}</h3>
                        <p class="text-[#4a2511]">{{ locale === 'ar' ? 'لا تحتاج لتحميل أو تسجيل. العب مباشرة من المتصفح.' : 'No download or registration required. Play directly in your browser.' }}</p>
                    </div>
                    <div class="bg-[#d3bfa1]/30 p-4 rounded">
                        <h3 class="text-lg text-[#8b2500] mb-2">{{ locale === 'ar' ? 'ثنائي اللغة' : 'Bilingual' }}</h3>
                        <p class="text-[#4a2511]">{{ locale === 'ar' ? 'واجهة كاملة بالعربية والإنجليزية مع دعم RTL.' : 'Full Arabic and English interface with RTL support.' }}</p>
                    </div>
                    <div class="bg-[#d3bfa1]/30 p-4 rounded">
                        <h3 class="text-lg text-[#8b2500] mb-2">{{ locale === 'ar' ? 'متعدد اللاعبين' : 'Multiplayer' }}</h3>
                        <p class="text-[#4a2511]">{{ locale === 'ar' ? 'من 3 إلى 10 لاعبين عبر الإنترنت في الوقت الحقيقي.' : '3 to 10 players online in real-time.' }}</p>
                    </div>
                    <div class="bg-[#d3bfa1]/30 p-4 rounded">
                        <h3 class="text-lg text-[#8b2500] mb-2">{{ locale === 'ar' ? 'تخصيص الشخصية' : 'Avatar Customization' }}</h3>
                        <p class="text-[#4a2511]">{{ locale === 'ar' ? 'شخصيات قابلة للتخصيص بألوان شعر ونظارات وذقن وأزياء متنوعة.' : 'Customizable avatars with hair, eyewear, chin styles, and costumes.' }}</p>
                    </div>
                    <div class="bg-[#d3bfa1]/30 p-4 rounded">
                        <h3 class="text-lg text-[#8b2500] mb-2">{{ locale === 'ar' ? 'فئات متعددة' : 'Word Categories' }}</h3>
                        <p class="text-[#4a2511]">{{ locale === 'ar' ? '9 فئات كلمات: حيوانات، طعام، أماكن، تكنولوجيا، رياضة، طبيعة، مهن، موسيقى، ومركبات.' : '9 word categories: animals, food, places, tech, sports, nature, professions, music, and vehicles.' }}</p>
                    </div>
                    <div class="bg-[#d3bfa1]/30 p-4 rounded">
                        <h3 class="text-lg text-[#8b2500] mb-2">{{ locale === 'ar' ? 'ذكاء اصطناعي' : 'AI-Powered' }}</h3>
                        <p class="text-[#4a2511]">{{ locale === 'ar' ? 'كلمات وتلميحات مولدة بالذكاء الاصطناعي لتنويع لا نهائي.' : 'AI-generated words and hints for infinite variety.' }}</p>
                    </div>
                </div>
            </section>

            <!-- Technical -->
            <section class="wanted-poster p-6 md:p-8">
                <h2 class="text-2xl md:text-3xl wanted-text mb-4 border-b border-dashed border-[#8b4513] pb-2">
                    {{ locale === 'ar' ? 'العب من أي مكان' : 'Play Anywhere' }}
                </h2>
                <p class="text-lg text-[#4a2511] leading-relaxed">
                    {{ locale === 'ar'
                        ? 'الخائن تعمل على جميع الأجهزة — الهاتف، الحاسوب اللوحي، والكمبيوتر. ثبّتها كتطبيق على هاتفك لتجربة أسرع. لا تحتاج لتحميل أي شيء من المتجر، فقط افتح الرابط وابدأ اللعب فوراً.'
                        : 'Traitor works on all devices — phone, tablet, and computer. Install it as an app on your phone for a faster experience. No app store download needed — just open the link and start playing instantly.'
                    }}
                </p>
            </section>

            <!-- CTA -->
            <div class="text-center py-8 space-y-4">
                <button @click="router.visit('/')" class="western-btn text-2xl md:text-4xl px-8 md:px-12 py-3 md:py-4">
                    {{ locale === 'ar' ? 'العب الآن!' : 'Play Now!' }}
                </button>
                <div class="flex flex-wrap justify-center gap-4 mt-4">
                    <a href="/how-to-play" class="text-[#d3bfa1] text-lg hover:text-[#f5e6d0] underline">
                        {{ locale === 'ar' ? 'كيف تلعب' : 'How to Play' }}
                    </a>
                    <a href="/faq" class="text-[#d3bfa1] text-lg hover:text-[#f5e6d0] underline">
                        {{ locale === 'ar' ? 'الأسئلة الشائعة' : 'FAQ' }}
                    </a>
                    <a href="/install" class="text-[#d3bfa1] text-lg hover:text-[#f5e6d0] underline">
                        {{ locale === 'ar' ? 'تثبيت التطبيق' : 'Install App' }}
                    </a>
                </div>
            </div>
        </div>
        <SiteFooter />
    </div>
</template>

<style scoped>
.wanted-poster { background-color: #e8dcc4; border: 2px solid #b8a07e; box-shadow: inset 0 0 30px rgba(139, 69, 19, 0.2), 0 3px 10px rgba(0,0,0,0.5); }
.wanted-text { color: #4a2511; text-shadow: 1px 1px 0px rgba(255,255,255,0.8); }
.western-btn { background-color: #8b2500; color: #e8dcc4; border: 3px solid #4a1500; box-shadow: 2px 2px 0px #3a1000; cursor: pointer; }
.western-btn:active { box-shadow: none; transform: translate(2px, 2px); }
</style>

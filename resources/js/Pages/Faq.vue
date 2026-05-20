<script setup>
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import NavBar from '../Components/NavBar.vue';
import SiteFooter from '../Components/SiteFooter.vue';

const { t, locale } = useI18n();

const faqs = [
    {
        q: { ar: 'ما هي لعبة الخائن؟', en: 'What is Traitor (al-Khaina)?' },
        a: { ar: 'الخائن هي لعبة استنتاج اجتماعي مجانية عبر الإنترنت بأسلوب الغرب الأمريكي. يتلقى جميع اللاعبين كلمة سرية باستثناء لاعب واحد (الخائن) الذي يحصل على تلميح غامض. يقدم اللاعبون تلميحات بكلمة واحدة ثم يصوتون لتحديد هوية الخائن. اللعبة متاحة بالعربية والإنجليزية وتدعم من 3 إلى 10 لاعبين.',
           en: 'Traitor (al-Khaina) is a free online social deduction party game with a Wild West theme. All players receive a secret word except one — the Traitor — who gets a vague hint. Players give one-word clues, then vote to identify the imposter. The game supports Arabic and English, and accommodates 3 to 10 players.' },
    },
    {
        q: { ar: 'كيف ألعب الخائن مع أصدقائي؟', en: 'How do I play Traitor with friends?' },
        a: { ar: 'أنشئ غرفة من الصفحة الرئيسية، شارك رمز الغرفة المكون من 6 أحرف مع أصدقائك، وعندما يكون الجميع جاهزاً، اضغط "بدء اللعبة". يمكن أيضاً الانضمام إلى الغرف العامة المتاحة.',
           en: 'Create a room from the home page, share the 6-character room code with friends, and when everyone is ready, press "Start Game". You can also join available public rooms.' },
    },
    {
        q: { ar: 'هل الخائن لعبة مجانية؟', en: 'Is Traitor free to play?' },
        a: { ar: 'نعم، الخائن لعبة مجانية تماماً. لا تحتاج لدفع أي شيء. يمكنك اللعب كضيف بدون تسجيل، أو إنشاء حساب لحفظ إحصائياتك وتخصيص شخصيتك.',
           en: 'Yes, Traitor is completely free to play. No payment required. You can play as a guest without registering, or create an account to save your stats and customize your avatar.' },
    },
    {
        q: { ar: 'ما هو الفرق بين الخائن وباقي اللاعبين؟', en: 'What is the difference between the Traitor and other players?' },
        a: { ar: 'الخائن يحصل على تلميح غامض وغير دقيق بدلاً من الكلمة الحقيقية. هدف الخائن هو الخداع والتظاهر بأنه يعرف الكلمة. هدف باقي اللاعبين (العصابة) هو اكتشاف هوية الخائن من خلال التلميحات والتصويت.',
           en: 'The Traitor receives a vague, imprecise hint instead of the real word. The Traitor\'s goal is to deceive others and pretend to know the word. The crew\'s goal is to identify the Traitor through clues and voting.' },
    },
    {
        q: { ar: 'كم عدد الجولات في اللعبة؟', en: 'How many rounds are in a game?' },
        a: { ar: 'يمكن لمنشئ الغرفة اختيار عدد الجولات من 1 إلى 5. الإعداد الافتراضي هو 3 جولات. في كل جولة، يتناوب اللاعبون على تقديم تلميحات، ثم يصوتون.',
           en: 'The room creator can choose 1 to 5 rounds. The default is 3 rounds. In each round, players take turns giving clues, then vote to identify the Traitor.' },
    },
    {
        q: { ar: 'هل يمكنني اللعب على الهاتف؟', en: 'Can I play on mobile?' },
        a: { ar: 'نعم! الخائن يعمل على جميع المتصفحات بما فيها هواتف_android و iPhone. يمكنك أيضاً تثبيت اللعبة كتطبيق (PWA) من خلال المتصفح لتجربة أسرع.',
           en: 'Yes! Traitor works on all browsers including Android and iPhone. You can also install it as a Progressive Web App (PWA) from your browser for a faster experience.' },
    },
    {
        q: { ar: 'ما هي فئات الكلمات المتاحة؟', en: 'What word categories are available?' },
        a: { ar: 'اللعبة تتضمن فئات متعددة: الحيوانات، الطعام، الأماكن، التكنولوجيا، الرياضة، الطبيعة، المهن، الموسيقى، والمركبات. يمكنك أيضاً اختيار "عشوائي" للحصول على خليط من جميع الفئات.',
           en: 'The game includes multiple categories: Animals, Food, Places, Technology, Sports, Nature, Professions, Music, and Vehicles. You can also choose "Random" for a mix of all categories.' },
    },
    {
        q: { ar: 'ما هي مستويات الصعوبة؟', en: 'What difficulty levels are available?' },
        a: { ar: 'هناك ثلاثة مستويات: سهل (كلمات شائعة وتلميحات أوضح)، متوسط (توازن بين الصعوبة والوضوح)، وصعب (كلمات نادرة وتلميحات أكثر غموضاً).',
           en: 'There are three levels: Easy (common words, clearer hints), Medium (balanced difficulty), and Hard (rare words, more ambiguous hints for the Traitor).' },
    },
    {
        q: { ar: 'كيف يعمل نظام النقاط؟', en: 'How does the scoring system work?' },
        a: { ar: 'يحصل أعضاء العصابة على نقاط عند اكتشاف الخائن، ويحصل الخائن على نقاط عند النجاة من التصويت. النقاط تتراكم عبر الجولات ويمكنك متابعة إحصائياتك من صفحة السجلات.',
           en: 'Crewmates earn points for correctly identifying the Traitor, and the Traitor earns points for surviving the vote. Points accumulate across rounds, and you can track your stats on the Records page.' },
    },
    {
        q: { ar: 'بماذا تختلف الخائن عن ألعاب مثل Spyfall أو Among Us؟', en: 'How is Traitor different from games like Spyfall or Among Us?' },
        a: { ar: 'الخائن تجمع بين آلية التلميحات الكلامية (مثل Codenames) مع الاستنتاج الاجتماعي (مثل Spyfall). على عكس Among Us التي تعتمد على الحركة، الخائن تركز على الكلمات والتلاعب اللفظي. كما أنها اللعبة الوحيدة التي تدعم العربية بشكل كامل.',
           en: 'Traitor combines word-based clue mechanics (like Codenames) with social deduction (like Spyfall). Unlike Among Us which relies on movement, Traitor focuses on words and verbal deception. It\'s also the only game that fully supports Arabic.' },
    },
];
</script>

<template>
    <Head>
        <title>Traitor Game FAQ — Frequently Asked Questions | الخائن</title>
        <meta name="description" content="Frequently asked questions about Traitor (al-Khaina), the free online social deduction word game. Learn about rules, gameplay, mobile support, categories, scoring, and more." head-key="description" />
        <meta property="og:title" content="Traitor Game FAQ — Frequently Asked Questions" head-key="og_title" />
        <meta property="og:description" content="Everything you need to know about Traitor (al-Khaina) — rules, setup, mobile play, scoring, and strategies. Free online social deduction game FAQ." head-key="og_description" />
    </Head>

    <div class="min-h-screen flex flex-col items-center p-4 md:p-8">
        <NavBar />

        <div class="max-w-3xl w-full space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'" :class="locale === 'ar' ? 'text-right' : 'text-left'">

            <div class="text-center mb-8">
                <h1 class="text-4xl md:text-5xl wanted-text uppercase mb-3">
                    {{ locale === 'ar' ? 'الأسئلة الشائعة' : 'Frequently Asked Questions' }}
                </h1>
                <p class="text-xl text-[#d3bfa1]">
                    {{ locale === 'ar' ? 'كل ما تحتاج معرفته عن لعبة الخائن' : 'Everything you need to know about Traitor' }}
                </p>
            </div>

            <div v-for="(faq, i) in faqs" :key="i" class="wanted-poster p-5 md:p-6">
                <h2 class="text-xl text-[#8b4513] mb-2">{{ faq.q[locale] || faq.q.en }}</h2>
                <p class="text-base md:text-lg text-[#4a2511] leading-relaxed">{{ faq.a[locale] || faq.a.en }}</p>
            </div>

            <div class="text-center py-8">
                <button @click="router.visit('/')" class="western-btn text-2xl md:text-3xl px-8 py-3">
                    {{ locale === 'ar' ? 'العب الآن!' : 'Play Now!' }}
                </button>
                <div class="mt-4">
                    <a href="/how-to-play" class="text-[#d3bfa1] text-lg hover:text-[#f5e6d0] underline">
                        {{ locale === 'ar' ? 'اقرأ دليل اللعب الكامل →' : 'Read the full How to Play guide →' }}
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

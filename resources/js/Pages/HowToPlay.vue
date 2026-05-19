<script setup>
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import NavBar from '../Components/NavBar.vue';

const { t, locale } = useI18n();
</script>

<template>
    <Head>
        <title>How to Play Traitor (الخائن) — Complete Rules & Strategy Guide</title>
        <meta name="description" content="Learn how to play Traitor (al-Khaina), the free online social deduction word game. Complete rules, step-by-step guide, tips for crew and imposter, and winning strategies." head-key="description" />
        <meta property="og:title" content="How to Play Traitor — Complete Rules & Strategy Guide" head-key="og_title" />
        <meta property="og:description" content="Master Traitor (al-Khaina)! Learn the rules, discover strategies for crew and imposter, and dominate the social deduction word game." head-key="og_description" />
    </Head>

    <div class="min-h-screen flex flex-col items-center p-4 md:p-8">
        <NavBar />

        <div class="max-w-3xl w-full space-y-8" :dir="locale === 'ar' ? 'rtl' : 'ltr'" :class="locale === 'ar' ? 'text-right' : 'text-left'">

            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-4xl md:text-5xl wanted-text uppercase mb-3">
                    {{ locale === 'ar' ? 'كيف تلعب الخائن؟' : 'How to Play Traitor' }}
                </h1>
                <p class="text-xl text-[#d3bfa1] max-w-xl mx-auto">
                    {{ locale === 'ar'
                        ? 'الدليل الكامل لقواعد واستراتيجيات لعبة الخائن الاجتماعية'
                        : 'The complete guide to rules, strategies, and winning at Traitor (al-Khaina)'
                    }}
                </p>
            </div>

            <!-- What is Traitor -->
            <section class="wanted-poster p-6 md:p-8">
                <h2 class="text-2xl md:text-3xl wanted-text mb-4 border-b border-dashed border-[#8b4513] pb-2">
                    {{ locale === 'ar' ? 'ما هي لعبة الخائن؟' : 'What is Traitor?' }}
                </h2>
                <p class="text-lg text-[#4a2511] leading-relaxed mb-4">
                    {{ locale === 'ar'
                        ? 'الخائن هي لعبة استنتاج اجتماعي مجانية عبر الإنترنت. يتلقى جميع اللاعبين كلمة سرية باستثناء لاعب واحد — الخائن — الذي يتلقى تلميحاً غامضاً بدلاً من الكلمة الحقيقية. يجب على اللاعبين تقديم تلميحات بكلمة واحدة ثم التصويت لتحديد هوية الخائن.'
                        : 'Traitor (al-Khaina) is a free online social deduction party game. All players receive a secret word — except one. The Traitor gets a vague, misleading hint instead. Players must give one-word clues, then vote to identify who the imposter is.'
                    }}
                </p>
                <p class="text-lg text-[#4a2511] leading-relaxed">
                    {{ locale === 'ar'
                        ? 'اللعبة مصممة بأسلوب الغرب الأمريكي القديم ويمكن لعبها باللغتين العربية والإنجليزية. تدعم من 3 إلى 10 لاعبين وهي مثالية للعب الجماعي مع الأصدقاء.'
                        : 'The game features a Wild West theme and supports both Arabic and English. It accommodates 3 to 10 players and is perfect for party nights with friends.'
                    }}
                </p>
            </section>

            <!-- Setup -->
            <section class="wanted-poster p-6 md:p-8">
                <h2 class="text-2xl md:text-3xl wanted-text mb-4 border-b border-dashed border-[#8b4513] pb-2">
                    {{ locale === 'ar' ? 'إعداد اللعبة' : 'Game Setup' }}
                </h2>
                <ol class="space-y-4 text-lg text-[#4a2511]">
                    <li class="flex gap-3">
                        <span class="text-2xl font-bold text-[#8b2500] shrink-0">1.</span>
                        <div>
                            <strong>{{ locale === 'ar' ? 'أنشئ غرفة' : 'Create a Room' }}</strong>
                            <p>{{ locale === 'ar' ? 'اختر اسمك المستعار، شخصيتك، وإعدادات الغرفة (عامة أو خاصة، عدد اللاعبين، عدد الجولات، اللغة، الفئة، مستوى الصعوبة).' : 'Pick your nickname, customize your avatar, and choose room settings (public or private, player count, rounds, language, word category, difficulty).' }}</p>
                        </div>
                    </li>
                    <li class="flex gap-3">
                        <span class="text-2xl font-bold text-[#8b2500] shrink-0">2.</span>
                        <div>
                            <strong>{{ locale === 'ar' ? 'ادعُ أصدقاءك' : 'Invite Friends' }}</strong>
                            <p>{{ locale === 'ar' ? 'شارك رمز الغرفة مع أصدقائك. يمكنهم الانضمام عبر إدخال الرمز في الصفحة الرئيسية. أو انضم إلى غرفة عامة متاحة.' : 'Share the room code with your friends. They can join by entering the code on the home page. Or join any available public room.' }}</p>
                        </div>
                    </li>
                    <li class="flex gap-3">
                        <span class="text-2xl font-bold text-[#8b2500] shrink-0">3.</span>
                        <div>
                            <strong>{{ locale === 'ar' ? 'استعد للعب' : 'Get Ready' }}</strong>
                            <p>{{ locale === 'ar' ? 'اضغط على زر "جاهز" عندما تكون مستعداً. يحتاج 3 لاعبين على الأقل ليكونوا جاهزين قبل أن يتمكن المنشئ من بدء اللعبة.' : 'Press "Ready" when prepared. At least 3 players must be ready before the room creator can start the game.' }}</p>
                        </div>
                    </li>
                </ol>
            </section>

            <!-- Gameplay -->
            <section class="wanted-poster p-6 md:p-8">
                <h2 class="text-2xl md:text-3xl wanted-text mb-4 border-b border-dashed border-[#8b4513] pb-2">
                    {{ locale === 'ar' ? 'مراحل اللعب' : 'Gameplay Phases' }}
                </h2>

                <div class="space-y-6">
                    <div class="bg-[#d3bfa1]/50 p-4 border-l-4 border-[#8b2500]">
                        <h3 class="text-xl text-[#8b2500] mb-2">{{ locale === 'ar' ? 'المرحلة ١: التلميحات' : 'Phase 1: Hints' }}</h3>
                        <p class="text-[#4a2511]">{{ locale === 'ar'
                            ? 'يحصل كل لاعب على كلمة سرية (ما عدا الخائن الذي يحصل على تلميح غامض). بالتناوب، يقدم كل لاعب تلميحاً بكلمة واحدة فقط. الهدف هو إعطاء تلميحات مفيدة لأعضاء العصابة دون أن يكتشف الخائن الكلمة الحقيقية.'
                            : 'Each player receives a secret word (except the Traitor, who gets a vague hint). Players take turns giving one-word clues. The goal is to help crewmates without revealing the word to the Traitor.'
                        }}</p>
                    </div>

                    <div class="bg-[#d3bfa1]/50 p-4 border-l-4 border-[#8b6914]">
                        <h3 class="text-xl text-[#8b6914] mb-2">{{ locale === 'ar' ? 'المرحلة ٢: التصويت' : 'Phase 2: Voting' }}</h3>
                        <p class="text-[#4a2511]">{{ locale === 'ar'
                            ? 'بعد تقديم جميع التلميحات، يناقش اللاعبون ويتصوتون لتحديد من هو الخائن. كل لاعب يصوت للاعب واحد. اللاعب الحاصل على أكبر عدد من الأصوات يتم "شنقه".'
                            : 'After all hints are submitted, players discuss and vote to identify the Traitor. Each player votes for one suspect. The player with the most votes gets "hanged".'
                        }}</p>
                    </div>

                    <div class="bg-[#d3bfa1]/50 p-4 border-l-4 border-[#1b4a1b]">
                        <h3 class="text-xl text-[#1b4a1b] mb-2">{{ locale === 'ar' ? 'المرحلة ٣: النتائج' : 'Phase 3: Results' }}</h3>
                        <p class="text-[#4a2511]">{{ locale === 'ar'
                            ? 'يتم كشف هوية الخائن والكلمة الحقيقية. إذا أصاب العصابة التصويت، يفوز الفريق. إذا نجا الخائن، يفوز هو. تستمر اللعبة لعدة جولات حتى يفوز فريق.'
                            : 'The Traitor\'s identity and the real word are revealed. If the crew voted correctly, the crew wins. If the Traitor survived, the Traitor wins. The game continues for multiple rounds until a team wins.'
                        }}</p>
                    </div>
                </div>
            </section>

            <!-- Strategies -->
            <section class="wanted-poster p-6 md:p-8">
                <h2 class="text-2xl md:text-3xl wanted-text mb-4 border-b border-dashed border-[#8b4513] pb-2">
                    {{ locale === 'ar' ? 'استراتيجيات الفوز' : 'Winning Strategies' }}
                </h2>

                <div class="grid md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <h3 class="text-xl text-[#1b4a1b]">{{ locale === 'ar' ? 'للعصابة (الطاقم)' : 'For the Crew' }}</h3>
                        <ul class="space-y-2 text-[#4a2511]">
                            <li>{{ locale === 'ar' ? '• قدم تلميحات محددة لكن ليست واضحة جداً' : '• Give specific but not obvious clues' }}</li>
                            <li>{{ locale === 'ar' ? '• راقب ردود أفعال اللاعبين على التلميحات' : '• Watch how players react to clues' }}</li>
                            <li>{{ locale === 'ar' ? '• الخائن غالباً يعطي تلميحات عامة وغير محددة' : '• The Traitor usually gives generic, vague clues' }}</li>
                            <li>{{ locale === 'ar' ? '• لا تصوت عشوائياً — الخطأ يفيد الخائن' : '• Don\'t vote randomly — mistakes help the Traitor' }}</li>
                            <li>{{ locale === 'ar' ? '• انتبه لمن يتجنب تقديم تلميحات مفيدة' : '• Notice who avoids giving helpful clues' }}</li>
                        </ul>
                    </div>
                    <div class="space-y-4">
                        <h3 class="text-xl text-[#8b2500]">{{ locale === 'ar' ? 'للخائن' : 'For the Traitor' }}</h3>
                        <ul class="space-y-2 text-[#4a2511]">
                            <li>{{ locale === 'ar' ? '• استمع لتلميحات الآخرين لاستنتاج الكلمة' : '• Listen to others\' clues to guess the word' }}</li>
                            <li>{{ locale === 'ar' ? '• قدم تلميحات تبدو طبيعية لكن ليست محددة' : '• Give clues that sound natural but aren\'t specific' }}</li>
                            <li>{{ locale === 'ar' ? '• لا تكن أول من يتهم شخصاً آخر' : '• Don\'t be the first to accuse someone else' }}</li>
                            <li>{{ locale === 'ar' ? '• تصرف بثقة — التردد يثير الشبهات' : '• Act confident — hesitation raises suspicion' }}</li>
                            <li>{{ locale === 'ar' ? '• إذا استنتجت الكلمة، استخدم تلميحاً مشابهاً' : '• If you figure out the word, give a similar clue' }}</li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- FAQ Schema-compatible section -->
            <section class="wanted-poster p-6 md:p-8">
                <h2 class="text-2xl md:text-3xl wanted-text mb-4 border-b border-dashed border-[#8b4513] pb-2">
                    {{ locale === 'ar' ? 'أسئلة شائعة' : 'Common Questions' }}
                </h2>
                <div class="space-y-4">
                    <div class="border-b border-dashed border-[#b8a07e] pb-3">
                        <h3 class="text-lg text-[#8b4513] mb-1">{{ locale === 'ar' ? 'كم عدد اللاعبين المطلوب؟' : 'How many players do I need?' }}</h3>
                        <p class="text-[#4a2511]">{{ locale === 'ar' ? 'تحتاج إلى 3 لاعبين على الأقل، ويمكن أن يصل العدد إلى 10 لاعبين.' : 'You need a minimum of 3 players, and can have up to 10 players per game.' }}</p>
                    </div>
                    <div class="border-b border-dashed border-[#b8a07e] pb-3">
                        <h3 class="text-lg text-[#8b4513] mb-1">{{ locale === 'ar' ? 'هل اللعبة مجانية؟' : 'Is the game free?' }}</h3>
                        <p class="text-[#4a2511]">{{ locale === 'ar' ? 'نعم! الخائن لعبة مجانية تماماً. لا تحتاج للتسجيل للعب كضيف.' : 'Yes! Traitor is completely free to play. You don\'t even need to register — play as a guest.' }}</p>
                    </div>
                    <div class="border-b border-dashed border-[#b8a07e] pb-3">
                        <h3 class="text-lg text-[#8b4513] mb-1">{{ locale === 'ar' ? 'هل يمكنني اللعب بالعربية؟' : 'Can I play in Arabic?' }}</h3>
                        <p class="text-[#4a2511]">{{ locale === 'ar' ? 'نعم! اللعبة متاحة بالعربية والإنجليزية. يمكنك اختيار لغة الغرفة عند إنشائها.' : 'Yes! The game supports both Arabic and English. You can choose the room language when creating a game.' }}</p>
                    </div>
                </div>
            </section>

            <!-- CTA -->
            <div class="text-center py-8">
                <button @click="router.visit('/')" class="western-btn text-2xl md:text-4xl px-8 md:px-12 py-3 md:py-4">
                    {{ locale === 'ar' ? 'العب الآن!' : 'Play Now!' }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.wanted-poster { background-color: #e8dcc4; border: 2px solid #b8a07e; box-shadow: inset 0 0 30px rgba(139, 69, 19, 0.2), 0 3px 10px rgba(0,0,0,0.5); }
.wanted-text { color: #4a2511; text-shadow: 1px 1px 0px rgba(255,255,255,0.8); }
.western-btn { background-color: #8b2500; color: #e8dcc4; border: 3px solid #4a1500; box-shadow: 2px 2px 0px #3a1000; cursor: pointer; transition: all 0.1s; }
.western-btn:active { box-shadow: none; transform: translate(2px, 2px); }
</style>

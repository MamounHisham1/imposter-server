<script setup>
import { useI18n } from 'vue-i18n';

const { locale } = useI18n();
</script>

<template>
    <footer class="w-full mt-8 text-center text-[#8b6914] text-sm space-y-2 pb-4 px-4">
        <div class="flex flex-wrap justify-center gap-3">
            <a href="/how-to-play" class="hover:text-[#d3bfa1]">{{ locale === 'ar' ? 'كيف تلعب' : 'How to Play' }}</a>
            <span>|</span>
            <a href="/faq" class="hover:text-[#d3bfa1]">{{ locale === 'ar' ? 'الأسئلة الشائعة' : 'FAQ' }}</a>
            <span>|</span>
            <a href="/about" class="hover:text-[#d3bfa1]">{{ locale === 'ar' ? 'عن اللعبة' : 'About' }}</a>
            <span>|</span>
            <a href="/install" class="hover:text-[#d3bfa1]">{{ locale === 'ar' ? 'تثبيت التطبيق' : 'Install App' }}</a>
            <span>|</span>
            <a href="/stats" class="hover:text-[#d3bfa1]">{{ locale === 'ar' ? 'الإحصائيات' : 'Stats' }}</a>
        </div>
        <p class="text-[#8b6914]/60">{{ locale === 'ar' ? 'الخائن — لعبة استنتاج اجتماعي مجانية' : 'Traitor (al-Khaina) — Free Social Deduction Game' }}</p>
    </footer>
</template>

<script setup lang="ts">
const { t } = useI18n()
const localePath = useLocalePath()
</script>

<template>
  <footer class="bg-gray-900 text-gray-400">
    <!-- 主要内容 -->
    <div class="container mx-auto px-4 py-12">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
        <!-- 品牌介绍 -->
        <div>
          <NuxtLink :to="localePath('/')" class="text-xl font-bold text-white tracking-wide">
            Jersey<span class="text-accent">Holic</span>
          </NuxtLink>
          <p class="mt-3 text-sm leading-relaxed">
            Premium sports jerseys for fans worldwide. Quality guaranteed, worldwide shipping.
          </p>
          <!-- 社交媒体图标占位 -->
          <div class="flex gap-3 mt-4">
            <a href="#" class="w-8 h-8 rounded-full bg-white/10 hover:bg-accent flex items-center justify-center transition" aria-label="Facebook">
              <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24">
                <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/>
              </svg>
            </a>
            <a href="#" class="w-8 h-8 rounded-full bg-white/10 hover:bg-accent flex items-center justify-center transition" aria-label="Instagram">
              <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <rect x="2" y="2" width="20" height="20" rx="5" ry="5" stroke-width="2"/>
                <path stroke-width="2" d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/>
                <line x1="17.5" y1="6.5" x2="17.51" y2="6.5" stroke-width="2"/>
              </svg>
            </a>
            <a href="#" class="w-8 h-8 rounded-full bg-white/10 hover:bg-accent flex items-center justify-center transition" aria-label="Twitter/X">
              <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24">
                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
              </svg>
            </a>
          </div>
        </div>

        <!-- 关于我们 -->
        <div>
          <h4 class="text-white font-semibold mb-4">{{ t('footer.about') }}</h4>
          <ul class="space-y-2 text-sm">
            <li><a href="#" class="hover:text-white transition">About JerseyHolic</a></li>
            <li><NuxtLink :to="localePath('/privacy')" class="hover:text-white transition">{{ t('footer.privacy') }}</NuxtLink></li>
            <li><NuxtLink :to="localePath('/terms')" class="hover:text-white transition">{{ t('footer.terms') }}</NuxtLink></li>
          </ul>
        </div>

        <!-- 客服支持 -->
        <div>
          <h4 class="text-white font-semibold mb-4">{{ t('footer.contact') }}</h4>
          <ul class="space-y-2 text-sm">
            <li><a href="#" class="hover:text-white transition">{{ t('footer.faq') }}</a></li>
            <li><a href="#" class="hover:text-white transition">{{ t('footer.shipping_policy') }}</a></li>
            <li><a href="#" class="hover:text-white transition">{{ t('footer.return_policy') }}</a></li>
            <li><a href="mailto:support@jerseyholic.com" class="hover:text-white transition">support@jerseyholic.com</a></li>
          </ul>
        </div>

        <!-- 支付方式 -->
        <div>
          <h4 class="text-white font-semibold mb-4">Secure Payment</h4>
          <div class="flex flex-wrap gap-2">
            <!-- 支付方式图标占位 -->
            <div v-for="method in ['Visa', 'MC', 'PayPal', 'AMEX']" :key="method"
              class="bg-white/10 rounded px-2 py-1 text-xs text-white font-mono">
              {{ method }}
            </div>
          </div>
          <p class="mt-4 text-xs">🔒 SSL Secured Checkout</p>
        </div>
      </div>
    </div>

    <!-- 版权栏 -->
    <div class="border-t border-white/10">
      <div class="container mx-auto px-4 py-4 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs">
        <p>{{ t('footer.copyright') }}</p>
        <div class="flex gap-4">
          <NuxtLink :to="localePath('/privacy')" class="hover:text-white transition">{{ t('footer.privacy') }}</NuxtLink>
          <NuxtLink :to="localePath('/terms')" class="hover:text-white transition">{{ t('footer.terms') }}</NuxtLink>
        </div>
      </div>
    </div>
  </footer>
</template>

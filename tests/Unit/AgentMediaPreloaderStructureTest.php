<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AgentMediaPreloaderStructureTest extends TestCase
{
    public function test_preloader_is_cached_fail_safe_and_uses_all_settled(): void
    {
        $root = dirname(__DIR__, 2);
        $preloader = file_get_contents($root.'/resources/js/agents/agentMediaPreloader.js');

        $this->assertStringContainsString('const preloadedUrls = new Set()', $preloader);
        $this->assertStringContainsString('const preloadedVideoElements = new Map()', $preloader);
        $this->assertStringContainsString('preloadedVideoElements.set(src, video)', $preloader);
        $this->assertStringContainsString('let preloadPromise = null', $preloader);
        $this->assertStringContainsString('Promise.allSettled', $preloader);
        $this->assertStringContainsString('Promise.race', $preloader);
        $this->assertStringContainsString('GLOBAL_TIMEOUT_MS = 18_000', $preloader);
        $this->assertStringContainsString('VIDEO_TIMEOUT_MS = 10_000', $preloader);
        $this->assertStringContainsString('IMAGE_TIMEOUT_MS = 8_000', $preloader);
        $this->assertStringContainsString("video.preload = 'auto'", $preloader);
        $this->assertStringContainsString('video.muted = true', $preloader);
        $this->assertStringContainsString('video.playsInline = true', $preloader);
        $this->assertStringContainsString('video.load()', $preloader);
        $this->assertStringContainsString('new Image()', $preloader);
        $this->assertStringContainsString('import.meta.env?.DEV', $preloader);
    }

    public function test_homepage_preloads_only_inside_entry_click_navigation(): void
    {
        $welcome = file_get_contents(dirname(__DIR__, 2).'/resources/js/Pages/Welcome.vue');

        $this->assertStringContainsString("prepareAndNavigate('/learner/access')", $welcome);
        $this->assertStringContainsString('prepareAndNavigate(dashboardLink.href)', $welcome);
        $this->assertStringContainsString("router.visit(href", $welcome);
        $this->assertStringContainsString('preloadAgentMedia()', $welcome);
        $this->assertStringContainsString('wait(2_000)', $welcome);
        $this->assertStringContainsString('ReaDirectBookLoader', $welcome);
        $this->assertStringContainsString('/admin/dashboard', $welcome);
        $this->assertStringContainsString('/teacher/dashboard', $welcome);
        $this->assertStringContainsString('dashboardPreloadsAgents', $welcome);
        $this->assertStringContainsString('v-else-if="dashboardPreloadsAgents"', $welcome);
        $this->assertStringNotContainsString('onMounted', $welcome);
    }

    public function test_preload_registry_contains_three_images_and_fourteen_interaction_videos(): void
    {
        $registry = file_get_contents(dirname(__DIR__, 2).'/resources/js/utils/agentMedia.js');

        $this->assertSame(3, substr_count($registry, "idle: image('images/"));
        $this->assertSame(14, substr_count($registry, "video('videos/"));
        $this->assertDoesNotMatchRegularExpression('/videos\/(?:Ciel|Vivian|Estelle)\/[cve]-idle\.mp4/', $registry);
        $this->assertDoesNotMatchRegularExpression('/idle\s+v\.mp4/', $registry);
    }

    public function test_book_loader_is_accessible_css_only_and_plain(): void
    {
        $loader = file_get_contents(dirname(__DIR__, 2).'/resources/js/Components/Loading/ReaDirectBookLoader.vue');

        $this->assertStringContainsString('role="status"', $loader);
        $this->assertStringContainsString('aria-live="polite"', $loader);
        $this->assertStringContainsString('@keyframes readirect-page-flip', $loader);
        $this->assertStringContainsString('rotateY', $loader);
        $this->assertStringContainsString('prefers-reduced-motion', $loader);
        $this->assertStringNotContainsString('<img', $loader);
        $this->assertStringNotContainsString('.gif', strtolower($loader));
        $this->assertStringNotContainsString('box-shadow', $loader);
        $this->assertStringNotContainsString('text-shadow', $loader);
        $this->assertStringNotContainsString('filter:', $loader);
        $this->assertStringNotContainsString('gradient', strtolower($loader));
    }
}

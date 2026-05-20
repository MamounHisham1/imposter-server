<template>
    <div class="min-h-screen bg-[#1a1008] text-[#e8d5b5]">
        <!-- Header -->
        <div class="bg-[#2b1d14] border-b border-[#4a3020] px-6 py-4">
            <div class="max-w-7xl mx-auto flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-[#8b2500] flex items-center justify-center text-white text-xl">A</div>
                    <div>
                        <h1 class="text-2xl text-[#f0e0c8]">Admin Dashboard</h1>
                        <p class="text-sm text-[#a08a6a]">Imposter Game Analytics</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <select v-model="dateRange" @change="refreshAll" class="bg-[#3b2a1a] border border-[#5a4030] rounded-lg px-3 py-2 text-sm text-[#e8d5b5]">
                        <option value="7">Last 7 days</option>
                        <option value="14">Last 14 days</option>
                        <option value="30">Last 30 days</option>
                        <option value="90">Last 90 days</option>
                    </select>
                    <a href="/" class="bg-[#3b2a1a] hover:bg-[#4a3525] border border-[#5a4030] px-4 py-2 rounded-lg text-sm transition-colors">Back to Game</a>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-6 py-6 space-y-6">
            <!-- ═══════ VISITOR ANALYTICS ═══════ -->

            <!-- Visitor KPI Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3">
                <div class="bg-[#2b1d14] rounded-xl border border-[#4a3020] p-4">
                    <div class="text-xs text-[#a08a6a] mb-1">Total Visitors</div>
                    <div class="text-2xl text-[#f0e0c8]">{{ visitorOverview.total_visitors }}</div>
                    <div class="text-xs mt-1" :class="visitorOverview.visitors_change >= 0 ? 'text-[#2d6a4f]' : 'text-[#dc2626]'">
                        {{ visitorOverview.visitors_change >= 0 ? '+' : '' }}{{ visitorOverview.visitors_change }}% vs prev day
                    </div>
                </div>
                <div class="bg-[#2b1d14] rounded-xl border border-[#4a3020] p-4">
                    <div class="text-xs text-[#a08a6a] mb-1">Page Views</div>
                    <div class="text-2xl text-[#f0e0c8]">{{ visitorOverview.total_page_views }}</div>
                    <div class="text-xs text-[#a08a6a] mt-1">{{ visitorOverview.avg_page_views_per_session }}/session</div>
                </div>
                <div class="bg-[#2b1d14] rounded-xl border border-[#4a3020] p-4">
                    <div class="text-xs text-[#a08a6a] mb-1">Sessions</div>
                    <div class="text-2xl text-[#f0e0c8]">{{ visitorOverview.total_sessions }}</div>
                    <div class="text-xs text-[#a08a6a] mt-1">Total visits</div>
                </div>
                <div class="bg-[#2b1d14] rounded-xl border border-[#4a3020] p-4">
                    <div class="text-xs text-[#a08a6a] mb-1">New Visitors</div>
                    <div class="text-2xl text-[#2d6a4f]">{{ visitorOverview.total_new_visitors }}</div>
                    <div class="text-xs text-[#2d6a4f] mt-1">{{ visitorOverview.new_visitor_rate }}% of total</div>
                </div>
                <div class="bg-[#2b1d14] rounded-xl border border-[#4a3020] p-4">
                    <div class="text-xs text-[#a08a6a] mb-1">Returning</div>
                    <div class="text-2xl text-[#b45309]">{{ visitorOverview.total_returning_visitors }}</div>
                    <div class="text-xs text-[#b45309] mt-1">{{ visitorOverview.returning_rate }}% of total</div>
                </div>
                <div class="bg-[#2b1d14] rounded-xl border border-[#4a3020] p-4">
                    <div class="text-xs text-[#a08a6a] mb-1">Bounce Rate</div>
                    <div class="text-2xl" :class="visitorOverview.bounce_rate > 60 ? 'text-[#dc2626]' : 'text-[#2d6a4f]'">{{ visitorOverview.bounce_rate }}%</div>
                    <div class="text-xs text-[#a08a6a] mt-1">Left after 1 page</div>
                </div>
                <div class="bg-[#2b1d14] rounded-xl border border-[#4a3020] p-4">
                    <div class="text-xs text-[#a08a6a] mb-1">Avg Session</div>
                    <div class="text-2xl text-[#f0e0c8]">{{ visitorOverview.avg_session_duration }}</div>
                    <div class="text-xs text-[#a08a6a] mt-1">Duration</div>
                </div>
                <div class="bg-[#2b1d14] rounded-xl border border-[#4a3020] p-4">
                    <div class="text-xs text-[#a08a6a] mb-1">Today</div>
                    <div class="text-2xl text-[#7c3aed]">{{ visitorOverview.today_visitors }}</div>
                    <div class="text-xs text-[#a08a6a] mt-1">vs {{ visitorOverview.yesterday_visitors }} yesterday</div>
                </div>
            </div>

            <!-- Visitor Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-[#2b1d14] rounded-xl border border-[#4a3020] p-5">
                    <h3 class="text-lg mb-4 text-[#f0e0c8]">Visitors Over Time</h3>
                    <div class="h-64">
                        <Line :data="visitorsChartData" :options="chartOptions" />
                    </div>
                </div>
                <div class="bg-[#2b1d14] rounded-xl border border-[#4a3020] p-5">
                    <h3 class="text-lg mb-4 text-[#f0e0c8]">New vs Returning Visitors</h3>
                    <div class="h-64">
                        <Line :data="newVsReturningData" :options="chartOptions" />
                    </div>
                </div>
            </div>

            <!-- Visitor breakdown row: Device + Top Pages + Top Referrers -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-[#2b1d14] rounded-xl border border-[#4a3020] p-5">
                    <h3 class="text-lg mb-4 text-[#f0e0c8]">Device Breakdown</h3>
                    <div class="space-y-3">
                        <div v-for="device in deviceBreakdown" :key="device.type" class="flex items-center gap-3">
                            <span class="text-sm w-16 capitalize">{{ device.type }}</span>
                            <div class="flex-1 bg-[#1a1008] rounded-full h-4">
                                <div class="h-4 rounded-full transition-all" :class="deviceColor(device.type)" :style="{ width: device.percent + '%' }"></div>
                            </div>
                            <span class="text-sm w-20 text-right">{{ device.percent }}%</span>
                            <span class="text-xs text-[#a08a6a]">({{ device.count }})</span>
                        </div>
                    </div>
                    <div class="h-48 mt-4 flex items-center justify-center">
                        <Doughnut :data="deviceChartData" :options="doughnutOptions" />
                    </div>
                </div>

                <div class="bg-[#2b1d14] rounded-xl border border-[#4a3020] p-5">
                    <h3 class="text-lg mb-4 text-[#f0e0c8]">Top Pages</h3>
                    <div class="space-y-2">
                        <div v-for="page in topPages" :key="page.page" class="flex items-center gap-3">
                            <span class="flex-1 text-sm truncate">{{ page.page }}</span>
                            <span class="text-sm font-mono text-[#f0e0c8]">{{ page.views }}</span>
                        </div>
                        <div v-if="!topPages.length" class="text-center text-[#a08a6a] py-4 text-sm">No data yet</div>
                    </div>
                </div>

                <div class="bg-[#2b1d14] rounded-xl border border-[#4a3020] p-5">
                    <h3 class="text-lg mb-4 text-[#f0e0c8]">Top Referrers</h3>
                    <div class="space-y-2">
                        <div v-for="ref in topReferrers" :key="ref.referrer" class="flex items-center gap-3">
                            <span class="flex-1 text-sm truncate">{{ ref.referrer }}</span>
                            <span class="text-sm font-mono text-[#f0e0c8]">{{ ref.visits }}</span>
                        </div>
                        <div v-if="!topReferrers.length" class="text-center text-[#a08a6a] py-4 text-sm">Direct / no referrer</div>
                    </div>
                </div>
            </div>

            <!-- ═══════ GAME ANALYTICS ═══════ -->

            <div class="border-t border-[#4a3020] pt-6">
                <h2 class="text-xl text-[#f0e0c8] mb-4">Game Analytics</h2>
            </div>

            <!-- Game Overview Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                <StatCard label="Total Games" :value="overview.total_games" icon="G" color="#8b2500" />
                <StatCard label="Completed" :value="overview.total_completed" icon="C" color="#2d6a4f" />
                <StatCard label="Total Rounds" :value="overview.total_rounds" icon="R" color="#b45309" />
                <StatCard label="Total Players" :value="overview.total_players" icon="P" color="#1d4ed8" />
                <StatCard label="Avg Players/Game" :value="overview.avg_players_per_game" icon="A" color="#7c3aed" />
                <StatCard label="Avg Duration" :value="formatDuration(overview.avg_duration_seconds)" icon="T" color="#dc2626" />
            </div>

            <!-- Win Rate Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-[#2b1d14] rounded-xl border border-[#4a3020] p-5">
                    <div class="text-sm text-[#a08a6a] mb-2">Crew Win Rate</div>
                    <div class="text-3xl text-[#2d6a4f]">{{ overview.crew_win_rate }}%</div>
                    <div class="text-xs text-[#a08a6a] mt-1">Innocent prevails</div>
                </div>
                <div class="bg-[#2b1d14] rounded-xl border border-[#4a3020] p-5">
                    <div class="text-sm text-[#a08a6a] mb-2">Imposter Win Rate</div>
                    <div class="text-3xl text-[#8b2500]">{{ overview.imposter_win_rate }}%</div>
                    <div class="text-xs text-[#a08a6a] mt-1">Deception wins</div>
                </div>
                <div class="bg-[#2b1d14] rounded-xl border border-[#4a3020] p-5">
                    <div class="text-sm text-[#a08a6a] mb-2">Tie Rate</div>
                    <div class="text-3xl text-[#b45309]">{{ overview.tie_rate }}%</div>
                    <div class="text-xs text-[#a08a6a] mt-1">Undecided votes</div>
                </div>
                <div class="bg-[#2b1d14] rounded-xl border border-[#4a3020] p-5">
                    <div class="text-sm text-[#a08a6a] mb-2">Imposter Caught Rate</div>
                    <div class="text-3xl text-[#1d4ed8]">{{ overview.imposter_caught_rate }}%</div>
                    <div class="text-xs text-[#a08a6a] mt-1">Busted</div>
                </div>
            </div>

            <!-- Game Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-[#2b1d14] rounded-xl border border-[#4a3020] p-5">
                    <h3 class="text-lg mb-4 text-[#f0e0c8]">Games Over Time</h3>
                    <div class="h-64">
                        <Line :data="gamesChartData" :options="chartOptions" />
                    </div>
                </div>
                <div class="bg-[#2b1d14] rounded-xl border border-[#4a3020] p-5">
                    <h3 class="text-lg mb-4 text-[#f0e0c8]">Players Over Time</h3>
                    <div class="h-64">
                        <Line :data="playersChartData" :options="chartOptions" />
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-[#2b1d14] rounded-xl border border-[#4a3020] p-5">
                    <h3 class="text-lg mb-4 text-[#f0e0c8]">Round Outcomes</h3>
                    <div class="h-64 flex items-center justify-center">
                        <Doughnut :data="winDistributionData" :options="doughnutOptions" />
                    </div>
                </div>
                <div class="bg-[#2b1d14] rounded-xl border border-[#4a3020] p-5 col-span-2">
                    <h3 class="text-lg mb-4 text-[#f0e0c8]">Hourly Activity (All Time)</h3>
                    <div class="h-64">
                        <Bar :data="hourlyChartData" :options="barOptions" />
                    </div>
                </div>
            </div>

            <!-- Today's Stats -->
            <div class="bg-[#2b1d14] rounded-xl border border-[#4a3020] p-5">
                <h3 class="text-lg mb-4 text-[#f0e0c8]">Today's Activity</h3>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div>
                        <div class="text-2xl text-[#f0e0c8]">{{ todayStats.games_played }}</div>
                        <div class="text-xs text-[#a08a6a]">Games Played</div>
                    </div>
                    <div>
                        <div class="text-2xl text-[#f0e0c8]">{{ todayStats.rooms_created }}</div>
                        <div class="text-xs text-[#a08a6a]">Rooms Created</div>
                    </div>
                    <div>
                        <div class="text-2xl text-[#f0e0c8]">{{ todayStats.total_players_joined }}</div>
                        <div class="text-xs text-[#a08a6a]">Player Joins</div>
                    </div>
                    <div>
                        <div class="text-2xl text-[#2d6a4f]">{{ todayStats.crew_wins }}</div>
                        <div class="text-xs text-[#a08a6a]">Crew Wins</div>
                    </div>
                    <div>
                        <div class="text-2xl text-[#8b2500]">{{ todayStats.imposter_wins }}</div>
                        <div class="text-xs text-[#a08a6a]">Imposter Wins</div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Leaderboard -->
                <div class="bg-[#2b1d14] rounded-xl border border-[#4a3020] p-5">
                    <h3 class="text-lg mb-4 text-[#f0e0c8]">Player Leaderboard</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-[#4a3020]">
                                    <th class="text-left py-2 px-2 text-[#a08a6a]">#</th>
                                    <th class="text-left py-2 px-2 text-[#a08a6a]">Player</th>
                                    <th class="text-right py-2 px-2 text-[#a08a6a]">Score</th>
                                    <th class="text-right py-2 px-2 text-[#a08a6a]">Games</th>
                                    <th class="text-right py-2 px-2 text-[#a08a6a]">Win%</th>
                                    <th class="text-right py-2 px-2 text-[#a08a6a]">Avg/G</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(player, i) in leaderboard" :key="player.nickname" class="border-b border-[#3b2a1a] hover:bg-[#3b2a1a]">
                                    <td class="py-2 px-2 font-bold" :class="i < 3 ? 'text-[#b45309]' : 'text-[#a08a6a]'">{{ i + 1 }}</td>
                                    <td class="py-2 px-2">{{ player.nickname }}</td>
                                    <td class="py-2 px-2 text-right">{{ player.total_score }}</td>
                                    <td class="py-2 px-2 text-right text-[#a08a6a]">{{ player.games_played }}</td>
                                    <td class="py-2 px-2 text-right">{{ player.win_rate }}%</td>
                                    <td class="py-2 px-2 text-right text-[#a08a6a]">{{ player.avg_score_per_game }}</td>
                                </tr>
                                <tr v-if="!leaderboard.length">
                                    <td colspan="6" class="py-4 text-center text-[#a08a6a]">No players yet</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Games -->
                <div class="bg-[#2b1d14] rounded-xl border border-[#4a3020] p-5">
                    <h3 class="text-lg mb-4 text-[#f0e0c8]">Recent Games</h3>
                    <div class="space-y-3 max-h-96 overflow-y-auto">
                        <div v-for="game in recentGames" :key="game.id" class="bg-[#1a1008] rounded-lg p-3">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs px-2 py-0.5 rounded bg-[#3b2a1a] text-[#a08a6a]">{{ game.room_code }}</span>
                                    <span class="text-xs px-2 py-0.5 rounded" :class="game.language === 'ar' ? 'bg-[#8b2500]/30 text-[#ff6b35]' : 'bg-[#1d4ed8]/30 text-[#60a5fa]'">{{ game.language }}</span>
                                    <span class="text-xs text-[#a08a6a]">{{ game.player_count }} players</span>
                                </div>
                                <span class="text-xs text-[#a08a6a]">{{ formatTimeAgo(game.created_at) }}</span>
                            </div>
                            <div class="flex flex-wrap gap-1">
                                <span v-for="round in game.rounds" :key="round.round_number" class="text-xs px-2 py-0.5 rounded" :class="roundWinnerClass(round)">
                                    R{{ round.round_number }}: {{ round.winner }}
                                </span>
                            </div>
                            <div v-if="game.duration_seconds" class="text-xs text-[#a08a6a] mt-1">{{ formatDuration(game.duration_seconds) }}</div>
                        </div>
                        <div v-if="!recentGames.length" class="text-center text-[#a08a6a] py-4">No games yet</div>
                    </div>
                </div>
            </div>

            <!-- Word Stats -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-[#2b1d14] rounded-xl border border-[#4a3020] p-5">
                    <h3 class="text-lg mb-4 text-[#f0e0c8]">Most Used Words</h3>
                    <div class="space-y-2">
                        <div v-for="(word, i) in wordStats.most_used" :key="word.real_word" class="flex items-center gap-3">
                            <span class="text-xs text-[#a08a6a] w-6">{{ i + 1 }}</span>
                            <span class="flex-1">{{ word.real_word }}</span>
                            <div class="w-32 bg-[#1a1008] rounded-full h-2">
                                <div class="h-2 rounded-full bg-[#8b2500]" :style="{ width: wordBarWidth(word.times_used) }"></div>
                            </div>
                            <span class="text-sm text-[#a08a6a] w-8 text-right">{{ word.times_used }}</span>
                        </div>
                        <div v-if="!wordStats.most_used?.length" class="text-center text-[#a08a6a] py-4">No words yet</div>
                    </div>
                </div>

                <div class="bg-[#2b1d14] rounded-xl border border-[#4a3020] p-5">
                    <h3 class="text-lg mb-4 text-[#f0e0c8]">Word Catch Rates</h3>
                    <div class="space-y-2">
                        <div v-for="word in wordStats.win_rates" :key="word.word" class="flex items-center gap-3">
                            <span class="flex-1">{{ word.word }}</span>
                            <div class="w-32 bg-[#1a1008] rounded-full h-2">
                                <div class="h-2 rounded-full" :class="word.catch_rate >= 50 ? 'bg-[#2d6a4f]' : 'bg-[#8b2500]'" :style="{ width: word.catch_rate + '%' }"></div>
                            </div>
                            <span class="text-sm w-16 text-right" :class="word.catch_rate >= 50 ? 'text-[#2d6a4f]' : 'text-[#8b2500]'">{{ word.catch_rate }}%</span>
                            <span class="text-xs text-[#a08a6a]">({{ word.total }}x)</span>
                        </div>
                        <div v-if="!wordStats.win_rates?.length" class="text-center text-[#a08a6a] py-4">Not enough data</div>
                    </div>
                </div>
            </div>

            <!-- Crew vs Imposter Wins Over Time -->
            <div class="bg-[#2b1d14] rounded-xl border border-[#4a3020] p-5">
                <h3 class="text-lg mb-4 text-[#f0e0c8]">Crew vs Imposter Wins Over Time</h3>
                <div class="h-64">
                    <Line :data="crewVsImposterData" :options="chartOptions" />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Line, Doughnut, Bar } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler,
} from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, ArcElement, Title, Tooltip, Legend, Filler);

const props = defineProps({
    overview: { type: Object, default: () => ({}) },
    todayStats: { type: Object, default: () => ({}) },
    charts: { type: Object, default: () => ({}) },
    recentGames: { type: Array, default: () => [] },
    leaderboard: { type: Array, default: () => [] },
    wordStats: { type: Object, default: () => ({ most_used: [], win_rates: [] }) },
    hourlyActivity: { type: Object, default: () => ({ labels: [], data: [] }) },
    visitorOverview: { type: Object, default: () => ({}) },
    visitorCharts: { type: Object, default: () => ({}) },
    topPages: { type: Array, default: () => [] },
    topReferrers: { type: Array, default: () => [] },
    deviceBreakdown: { type: Array, default: () => [] },
});

const dateRange = ref('30');

// Local reactive copies for data that can be refreshed via API
const localCharts = ref(props.charts);
const localVisitorCharts = ref(props.visitorCharts);

const overview = computed(() => ({
    total_games: props.overview.total_games ?? 0,
    total_completed: props.overview.total_completed ?? 0,
    total_rounds: props.overview.total_rounds ?? 0,
    total_players: props.overview.total_players ?? 0,
    total_player_joins: props.overview.total_player_joins ?? 0,
    rooms_created: props.overview.rooms_created ?? 0,
    avg_players_per_game: props.overview.avg_players_per_game ?? 0,
    avg_duration_seconds: props.overview.avg_duration_seconds ?? 0,
    avg_rounds_per_game: props.overview.avg_rounds_per_game ?? 0,
    crew_win_rate: props.overview.crew_win_rate ?? 0,
    imposter_win_rate: props.overview.imposter_win_rate ?? 0,
    tie_rate: props.overview.tie_rate ?? 0,
    imposter_caught_rate: props.overview.imposter_caught_rate ?? 0,
}));

const visitorOverview = computed(() => ({
    total_page_views: props.visitorOverview.total_page_views ?? 0,
    total_visitors: props.visitorOverview.total_visitors ?? 0,
    total_sessions: props.visitorOverview.total_sessions ?? 0,
    total_new_visitors: props.visitorOverview.total_new_visitors ?? 0,
    total_returning_visitors: props.visitorOverview.total_returning_visitors ?? 0,
    new_visitor_rate: props.visitorOverview.new_visitor_rate ?? 0,
    returning_rate: props.visitorOverview.returning_rate ?? 0,
    bounce_rate: props.visitorOverview.bounce_rate ?? 0,
    avg_page_views_per_session: props.visitorOverview.avg_page_views_per_session ?? 0,
    today_visitors: props.visitorOverview.today_visitors ?? 0,
    yesterday_visitors: props.visitorOverview.yesterday_visitors ?? 0,
    visitors_change: props.visitorOverview.visitors_change ?? 0,
    avg_session_duration: props.visitorOverview.avg_session_duration ?? '0m 0s',
}));

const todayStats = computed(() => props.todayStats || {});
const recentGames = computed(() => props.recentGames || []);
const leaderboard = computed(() => props.leaderboard || []);
const wordStats = computed(() => props.wordStats || { most_used: [], win_rates: [] });
const topPages = computed(() => props.topPages || []);
const topReferrers = computed(() => props.topReferrers || []);
const deviceBreakdown = computed(() => props.deviceBreakdown || []);

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { labels: { color: '#a08a6a' } } },
    scales: {
        x: { ticks: { color: '#a08a6a', maxTicksLimit: 10 }, grid: { color: '#3b2a1a' } },
        y: { ticks: { color: '#a08a6a' }, grid: { color: '#3b2a1a' }, beginAtZero: true },
    },
};

const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { labels: { color: '#a08a6a' } } },
};

const barOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        x: { ticks: { color: '#a08a6a' }, grid: { color: '#3b2a1a' } },
        y: { ticks: { color: '#a08a6a' }, grid: { color: '#3b2a1a' }, beginAtZero: true },
    },
};

// Visitor charts
const visitorsChartData = computed(() => ({
    labels: localVisitorCharts.value?.labels?.map(d => d.substring(5)) || [],
    datasets: [{
        label: 'Unique Visitors',
        data: localVisitorCharts.value?.unique_visitors || [],
        borderColor: '#7c3aed',
        backgroundColor: 'rgba(124, 58, 237, 0.1)',
        fill: true,
        tension: 0.3,
    }, {
        label: 'Page Views',
        data: localVisitorCharts.value?.page_views || [],
        borderColor: '#64748b',
        backgroundColor: 'rgba(100, 116, 139, 0.1)',
        fill: true,
        tension: 0.3,
    }],
}));

const newVsReturningData = computed(() => ({
    labels: localVisitorCharts.value?.labels?.map(d => d.substring(5)) || [],
    datasets: [{
        label: 'New Visitors',
        data: localVisitorCharts.value?.new_visitors || [],
        borderColor: '#2d6a4f',
        backgroundColor: 'rgba(45, 106, 79, 0.15)',
        fill: true,
        tension: 0.3,
    }, {
        label: 'Returning Visitors',
        data: localVisitorCharts.value?.returning_visitors || [],
        borderColor: '#b45309',
        backgroundColor: 'rgba(180, 83, 9, 0.15)',
        fill: true,
        tension: 0.3,
    }],
}));

const deviceChartData = computed(() => {
    const devices = props.deviceBreakdown || [];
    return {
        labels: devices.map(d => d.type),
        datasets: [{
            data: devices.map(d => d.count),
            backgroundColor: ['#8b2500', '#2d6a4f', '#b45309'],
            borderColor: ['#ab4520', '#3b8a6f', '#d47319'],
            borderWidth: 2,
        }],
    };
});

// Game charts
const gamesChartData = computed(() => ({
    labels: localCharts.value?.labels?.map(d => d.substring(5)) || [],
    datasets: [{
        label: 'Games',
        data: localCharts.value?.games || [],
        borderColor: '#8b2500',
        backgroundColor: 'rgba(139, 37, 0, 0.1)',
        fill: true,
        tension: 0.3,
    }],
}));

const playersChartData = computed(() => ({
    labels: localCharts.value?.labels?.map(d => d.substring(5)) || [],
    datasets: [{
        label: 'Player Joins',
        data: localCharts.value?.players || [],
        borderColor: '#1d4ed8',
        backgroundColor: 'rgba(29, 78, 216, 0.1)',
        fill: true,
        tension: 0.3,
    }],
}));

const winDistributionData = computed(() => {
    const crew = localCharts.value?.crew_wins?.reduce((a, b) => a + b, 0) || 0;
    const imposter = localCharts.value?.imposter_wins?.reduce((a, b) => a + b, 0) || 0;
    const ties = (localCharts.value?.rounds || []).reduce((sum, r, i) => {
        return sum + Math.max(0, (localCharts.value?.rounds?.[i] || 0) - (localCharts.value?.crew_wins?.[i] || 0) - (localCharts.value?.imposter_wins?.[i] || 0));
    }, 0);

    return {
        labels: ['Crew Wins', 'Imposter Wins', 'Ties'],
        datasets: [{
            data: [crew, imposter, ties],
            backgroundColor: ['#2d6a4f', '#8b2500', '#b45309'],
            borderColor: ['#3b8a6f', '#ab4520', '#d47319'],
            borderWidth: 2,
        }],
    };
});

const crewVsImposterData = computed(() => ({
    labels: localCharts.value?.labels?.map(d => d.substring(5)) || [],
    datasets: [
        {
            label: 'Crew Wins',
            data: localCharts.value?.crew_wins || [],
            borderColor: '#2d6a4f',
            backgroundColor: 'rgba(45, 106, 79, 0.1)',
            fill: true,
            tension: 0.3,
        },
        {
            label: 'Imposter Wins',
            data: localCharts.value?.imposter_wins || [],
            borderColor: '#8b2500',
            backgroundColor: 'rgba(139, 37, 0, 0.1)',
            fill: true,
            tension: 0.3,
        },
    ],
}));

const hourlyChartData = computed(() => ({
    labels: props.hourlyActivity?.labels || [],
    datasets: [{
        label: 'Games',
        data: props.hourlyActivity?.data || [],
        backgroundColor: 'rgba(139, 37, 0, 0.6)',
        borderColor: '#8b2500',
        borderWidth: 1,
        borderRadius: 4,
    }],
}));

function deviceColor(type) {
    return { mobile: 'bg-[#8b2500]', desktop: 'bg-[#2d6a4f]', tablet: 'bg-[#b45309]' }[type] || 'bg-[#64748b]';
}

function formatDuration(seconds) {
    if (!seconds) return '0m';
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return m > 0 ? `${m}m ${s}s` : `${s}s`;
}

function formatTimeAgo(dateStr) {
    if (!dateStr) return '';
    const diff = Date.now() - new Date(dateStr).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    return `${Math.floor(hrs / 24)}d ago`;
}

function wordBarWidth(count) {
    const max = Math.max(...(props.wordStats?.most_used?.map(w => w.times_used) || [1]));
    return Math.max(5, (count / max) * 100) + '%';
}

function roundWinnerClass(round) {
    if (round.winner === 'crew') return 'bg-[#2d6a4f]/30 text-[#6ee7a0]';
    if (round.winner === 'imposter') return 'bg-[#8b2500]/30 text-[#ff6b35]';
    return 'bg-[#b45309]/30 text-[#fbbf24]';
}

function refreshAll() {
    const days = dateRange.value;
    fetch(`/admin/api/charts?days=${days}`).then(r => r.json()).then(data => { localCharts.value = data; });
    fetch(`/admin/api/visitor-charts?days=${days}`).then(r => r.json()).then(data => { localVisitorCharts.value = data; });
}
</script>

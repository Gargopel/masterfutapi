import React, { useEffect, useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { Activity, AlertTriangle, ArrowLeft, BarChart3, CheckCircle2, Clock3, Copy, Database, Eye, Globe2, KeyRound, Languages, LayoutDashboard, Link as LinkIcon, ListChecks, LogOut, Moon, Play, RefreshCw, Server, Shield, Sun, Table2, Users, XCircle, type LucideIcon } from 'lucide-react';
import './bootstrap';

type Locale = 'pt-BR' | 'en' | 'es' | 'zh';
type Theme = 'light' | 'dark';
type Provider = { id: number; name: string; slug: string; status: string; is_active: boolean; base_url?: string; website_url?: string; docs_url?: string; last_error?: string };
type SyncJob = { id: number; type: string; status: string; source?: string; is_incremental?: boolean; progress_percent: string | number; processed_items?: number; total_items?: number; created_items?: number; updated_items?: number; failed_items?: number; started_at?: string; finished_at?: string; cancel_requested_at?: string; last_error?: string; config?: unknown; result?: unknown; provider?: Provider; sport?: any; league?: any; season?: any };
type ApiKey = { id: number; name: string; key_hint?: string; is_active: boolean; provider?: Provider };
type RequestLog = { id: number; provider?: Provider; sync_job_id?: number; method: string; endpoint: string; status_code?: number; success: boolean; duration_ms?: number; requested_at: string; error_message?: string; response_excerpt?: string };
type SyncItem = { id: number; status: string; entity_type?: string; entity_id?: number; external_id?: string; action?: string; error_message?: string; raw_payload?: unknown; created_at?: string };
type HomeSettings = { brand_name: string; nav_badge: string; hero_title: string; hero_subtitle: string; hero_image_url: string; primary_cta_label: string; primary_cta_url: string; secondary_cta_label: string; secondary_cta_url: string; accent_color: string; features: { title: string; description: string }[] };
type PlanRule = { scope_type: string; region?: string | null; country_id?: number | string | null; league_id?: number | string | null; season_id?: number | string | null; country?: any; league?: any; season?: any };
type Plan = { id?: number; name: string; slug: string; description?: string; is_active: boolean; is_default: boolean; allow_all: boolean; requests_per_minute: number; max_active_api_keys: number; access_rules: PlanRule[]; users_count?: number };

const messages: Record<Locale, Record<string, string>> = {
  'pt-BR': { dashboard: 'Dashboard', users: 'Usuarios', plans: 'Planos', sports: 'Esportes', countries: 'Paises', leagues: 'Ligas', seasons: 'Temporadas', teams: 'Times', matches: 'Partidas', providers: 'Providers', keys: 'API Keys', sync: 'Sync Jobs', logs: 'Request Logs', settings: 'Settings', login: 'Entrar', password: 'Senha', email: 'Email', active: 'Ativo', inactive: 'Inativo', planned: 'Planejado', progress: 'Progresso', recent: 'Coletas recentes', test: 'Testar', run: 'Rodar', rerun: 'Reexecutar', cancel: 'Cancelar', back: 'Voltar', copy: 'Copiar', logout: 'Sair', docs: 'Docs', website: 'Site', baseUrl: 'Base URL', save: 'Salvar', createKey: 'Criar key', createSync: 'Criar job', jobDetail: 'Detalhe do job', requestLogs: 'Requests externas', syncItems: 'Itens do sync', coverage: 'Cobertura', providerHealth: 'Saude dos providers', emptyJobs: 'Nenhum sync job encontrado', emptyLogs: 'Nenhum log encontrado', emptyErrors: 'Nenhum erro registrado' },
  en: { dashboard: 'Dashboard', users: 'Users', sports: 'Sports', countries: 'Countries', leagues: 'Leagues', seasons: 'Seasons', teams: 'Teams', matches: 'Matches', providers: 'Providers', keys: 'API Keys', sync: 'Sync Jobs', logs: 'Request Logs', settings: 'Settings', login: 'Login', password: 'Password', email: 'Email', active: 'Active', inactive: 'Inactive', planned: 'Planned', progress: 'Progress', recent: 'Recent syncs', test: 'Test', run: 'Run', rerun: 'Rerun', cancel: 'Cancel', back: 'Back', copy: 'Copy', logout: 'Logout', docs: 'Docs', website: 'Website', baseUrl: 'Base URL', save: 'Save', createKey: 'Create key', createSync: 'Create job', jobDetail: 'Job detail', requestLogs: 'External requests', syncItems: 'Sync items', coverage: 'Coverage', providerHealth: 'Provider health', emptyJobs: 'No sync jobs found', emptyLogs: 'No logs found', emptyErrors: 'No errors recorded' },
  es: { dashboard: 'Panel', users: 'Usuarios', sports: 'Deportes', countries: 'Paises', leagues: 'Ligas', seasons: 'Temporadas', teams: 'Equipos', matches: 'Partidos', providers: 'Proveedores', keys: 'API Keys', sync: 'Sync Jobs', logs: 'Registros', settings: 'Ajustes', login: 'Entrar', password: 'Contrasena', email: 'Email', active: 'Activo', inactive: 'Inactivo', planned: 'Planeado', progress: 'Progreso', recent: 'Sincronizaciones', test: 'Probar', run: 'Ejecutar', rerun: 'Reejecutar', cancel: 'Cancelar', back: 'Volver', copy: 'Copiar', logout: 'Salir', docs: 'Docs', website: 'Sitio', baseUrl: 'Base URL', save: 'Guardar', createKey: 'Crear key', createSync: 'Crear job', jobDetail: 'Detalle del job', requestLogs: 'Requests externas', syncItems: 'Items del sync', coverage: 'Cobertura', providerHealth: 'Salud de proveedores', emptyJobs: 'No se encontraron sync jobs', emptyLogs: 'No se encontraron logs', emptyErrors: 'No hay errores registrados' },
  zh: { dashboard: '仪表板', sports: '运动', countries: '国家', leagues: '联赛', seasons: '赛季', teams: '球队', matches: '比赛', providers: '供应商', keys: 'API Keys', sync: '同步任务', logs: '请求日志', settings: '设置', login: '登录', password: '密码', email: '邮箱', active: '启用', inactive: '停用', planned: '计划中', progress: '进度', recent: '最近同步', test: '测试', run: '运行', rerun: '重新运行', cancel: '取消', back: '返回', copy: '复制', logout: '退出', docs: '文档', website: '网站', baseUrl: 'Base URL', save: '保存', createKey: '创建 Key', createSync: '创建任务', jobDetail: '任务详情', requestLogs: '外部请求', syncItems: '同步项目', coverage: '覆盖率', providerHealth: '供应商健康', emptyJobs: '没有同步任务', emptyLogs: '没有日志', emptyErrors: '没有错误记录' },
};

type NavItem = [string, LucideIcon];
const navGroups: { label: string; items: NavItem[] }[] = [
  { label: 'Operacao', items: [['dashboard', LayoutDashboard], ['providers', Server], ['sync', RefreshCw], ['schedules', Clock3], ['logs', ListChecks]] },
  { label: 'Produto', items: [['users', Users], ['plans', Shield], ['keys', KeyRound], ['settings', Languages]] },
  { label: 'Dados', items: [['sports', Activity], ['countries', Globe2], ['leagues', Table2], ['seasons', ListChecks], ['teams', Shield], ['matches', Database]] },
];

const csrf = () => decodeURIComponent(document.cookie.split('; ').find(cookie => cookie.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '');
async function api<T>(url: string, options: RequestInit = {}): Promise<T> {
  const response = await fetch(url, { headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': csrf(), ...(options.headers ?? {}) }, credentials: 'same-origin', ...options });
  if (!response.ok) throw new Error((await response.json().catch(() => ({}))).message ?? 'Request failed');
  return response.json();
}
const qs = (params: Record<string, string>) => new URLSearchParams(Object.entries(params).filter(([, v]) => v !== '')).toString();
const labelFor = (key: string) => ({
  active_providers: 'Providers ativos',
  active_api_keys: 'Chaves internas ativas',
  collected_leagues: 'Ligas coletadas',
  collected_teams: 'Times coletados',
  collected_matches: 'Partidas coletadas',
  finished_matches: 'Partidas finalizadas',
  future_matches: 'Partidas futuras',
  collected_statistics: 'Estatisticas coletadas',
  requests_today: 'Requests hoje',
  errors_today: 'Erros hoje',
  pending_sync_jobs: 'Jobs pendentes',
  ready_sync_jobs: 'Jobs prontos',
  scheduled_sync_jobs: 'Jobs agendados',
  running_sync_jobs: 'Jobs rodando',
  failed_sync_jobs: 'Jobs com falha',
  open_alerts: 'Alertas abertos',
  status_429_today: 'Rate limits hoje',
  cancelled_jobs: 'Jobs cancelados',
  active_schedules: 'Agendamentos ativos',
  users_total: 'Usuarios',
  users_with_active_keys: 'Usuarios com chave',
  active_user_api_keys: 'Chaves de clientes ativas',
  revoked_user_api_keys: 'Chaves revogadas',
  rate_limited_today: 'Bloqueios hoje',
}[key] ?? key.replace(/_/g, ' '));
const descriptionFor = (key: string) => ({
  active_providers: 'Fontes internas habilitadas para coleta.',
  collected_matches: 'Volume total disponivel para a API.',
  requests_today: 'Chamadas externas e operacionais no dia.',
  errors_today: 'Falhas registradas desde 00:00.',
  ready_sync_jobs: 'Pendentes que podem rodar agora.',
  scheduled_sync_jobs: 'Aguardando janela de execucao.',
  running_sync_jobs: 'Processamentos em andamento.',
  failed_sync_jobs: 'Precisam de revisao operacional.',
  open_alerts: 'Eventos que pedem atencao.',
}[key] ?? '');
const pageTitle = (id: string, t: (key: string) => string) => ({
  dashboard: 'Centro de controle',
  users: 'Usuarios e consumo',
  providers: 'Providers internos',
  keys: 'Chaves dos providers',
  plans: 'Planos de acesso',
  sync: 'Sync jobs',
  schedules: 'Agendamentos',
  logs: 'Logs externos',
  settings: 'Configuracoes da homepage',
}[id] ?? t(id));
const pageSubtitle = (id: string) => ({
  dashboard: 'Saude da operacao, fila, coleta de dados e alertas em um unico lugar.',
  users: 'Acompanhe clientes, API keys e consumo da MasterFut API.',
  providers: 'Gerencie as fontes internas de dados e dispare coletas completas.',
  keys: 'Controle as credenciais usadas nos providers externos.',
  plans: 'Defina limites por plano e quais ligas, temporadas ou regioes cada usuario pode acessar.',
  sync: 'Crie, acompanhe e reexecute coletas de dados esportivos.',
  schedules: 'Automatize rotinas recorrentes de sincronizacao.',
  logs: 'Audite chamadas feitas aos providers internos.',
  settings: 'Edite textos, imagem e CTAs da pagina publica.',
}[id] ?? 'Dados operacionais da MasterFut.');

function Badge({ value }: { value: string }) {
  const tone = ['active', 'completed', 'created'].includes(value) ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : ['planned', 'running', 'updated', 'pending'].includes(value) ? 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300' : ['failed', 'error', 'cancelled'].includes(value) ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300' : value === 'skipped' ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300';
  return <span className={`inline-flex rounded px-2 py-1 text-xs font-medium ${tone}`}>{value}</span>;
}
function Card({ children, className = '' }: React.PropsWithChildren<{ className?: string }>) { return <div className={`rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 ${className}`}>{children}</div>; }
function Loader() { return <div className="grid min-h-32 place-items-center"><RefreshCw className="h-5 w-5 animate-spin text-zinc-400" /></div>; }
function Empty({ children }: React.PropsWithChildren) { return <p className="rounded border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500 dark:border-zinc-700">{children}</p>; }
function StatCard({ label, value, description, tone = 'neutral', icon: Icon = BarChart3 }: { label: string; value: unknown; description?: string; tone?: 'neutral' | 'good' | 'warn' | 'bad'; icon?: React.ComponentType<{ className?: string }> }) {
  const toneClass = tone === 'good' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : tone === 'warn' ? 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300' : tone === 'bad' ? 'bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300';
  return <Card><div className="flex items-start justify-between gap-4"><div><p className="text-sm font-medium text-zinc-500 dark:text-zinc-400">{label}</p><p className="mt-2 text-3xl font-semibold tracking-normal">{String(value ?? 0)}</p>{description && <p className="mt-2 text-xs leading-5 text-zinc-500">{description}</p>}</div><span className={`grid h-10 w-10 shrink-0 place-items-center rounded ${toneClass}`}><Icon className="h-5 w-5" /></span></div></Card>;
}
function MiniStat({ label, value, tone = 'neutral', icon: Icon = BarChart3 }: { label: string; value: unknown; tone?: 'neutral' | 'good' | 'warn' | 'bad'; icon?: React.ComponentType<{ className?: string }> }) {
  const toneClass = tone === 'good' ? 'text-emerald-600' : tone === 'warn' ? 'text-amber-600' : tone === 'bad' ? 'text-rose-600' : 'text-sky-600';
  return <div className="rounded border border-zinc-200 p-3 dark:border-zinc-800"><div className="flex items-center justify-between gap-3"><p className="text-xs font-medium text-zinc-500">{label}</p><Icon className={`h-4 w-4 ${toneClass}`} /></div><p className="mt-2 text-xl font-semibold">{String(value ?? 0)}</p></div>;
}
function JsonBlock({ value, t }: { value: unknown; t: (key: string) => string }) {
  const text = JSON.stringify(value ?? {}, null, 2);
  return <div><button className="mb-2 inline-flex items-center gap-1 rounded border px-2 py-1 text-xs dark:border-zinc-700" onClick={() => navigator.clipboard?.writeText(text)}><Copy className="h-3 w-3" />{t('copy')}</button><pre className="max-h-80 overflow-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">{text}</pre></div>;
}

function Login({ onLogin, t }: { onLogin: () => void; t: (key: string) => string }) {
  const [email, setEmail] = useState('admin@futia.local');
  const [password, setPassword] = useState('password');
  const [error, setError] = useState('');
  return <main className="grid min-h-screen place-items-center p-6"><Card className="w-full max-w-sm"><div className="mb-6 flex items-center gap-3"><Database className="h-8 w-8 text-emerald-600" /><div><h1 className="text-xl font-semibold">FutIA Data Hub</h1><p className="text-sm text-zinc-500">Admin</p></div></div><form className="space-y-3" onSubmit={async e => { e.preventDefault(); setError(''); try { await api('/admin/api/login', { method: 'POST', body: JSON.stringify({ email, password }) }); onLogin(); } catch (err) { setError(err instanceof Error ? err.message : 'Login failed'); } }}><input className="w-full rounded border border-zinc-300 bg-transparent px-3 py-2 dark:border-zinc-700" value={email} onChange={e => setEmail(e.target.value)} placeholder={t('email')} /><input className="w-full rounded border border-zinc-300 bg-transparent px-3 py-2 dark:border-zinc-700" value={password} onChange={e => setPassword(e.target.value)} placeholder={t('password')} type="password" />{error && <p className="text-sm text-rose-600">{error}</p>}<button className="w-full rounded bg-emerald-600 px-3 py-2 font-medium text-white hover:bg-emerald-700">{t('login')}</button></form></Card></main>;
}

function Dashboard({ t }: { t: (key: string) => string }) {
  const [data, setData] = useState<any>();
  const [health, setHealth] = useState<any[]>([]);
  const [coverage, setCoverage] = useState<any>();
  useEffect(() => { api('/admin/api/dashboard').then(setData); api<any[]>('/admin/api/providers/health').then(setHealth); api('/admin/api/data-coverage').then(setCoverage); }, []);
  if (!data) return <Loader />;
  const cards = data.cards ?? {};
  const primary = [
    ['active_providers', cards.active_providers, 'good', Server],
    ['collected_matches', cards.collected_matches, 'neutral', Database],
    ['requests_today', cards.requests_today, 'neutral', BarChart3],
    ['errors_today', cards.errors_today, Number(cards.errors_today) > 0 ? 'bad' : 'good', AlertTriangle],
  ] as const;
  const queue = [
    ['ready_sync_jobs', cards.ready_sync_jobs, 'good', CheckCircle2],
    ['scheduled_sync_jobs', cards.scheduled_sync_jobs, 'warn', Clock3],
    ['running_sync_jobs', cards.running_sync_jobs, 'neutral', RefreshCw],
    ['failed_sync_jobs', cards.failed_sync_jobs, Number(cards.failed_sync_jobs) > 0 ? 'bad' : 'neutral', XCircle],
  ] as const;
  return <div className="space-y-4">
    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">{primary.map(([key, value, tone, Icon]) => <StatCard key={key} label={labelFor(key)} value={value} description={descriptionFor(key)} tone={tone} icon={Icon} />)}</div>
    <div className="grid gap-4 xl:grid-cols-[1.2fr_.8fr]">
      <Card><div className="mb-4 flex items-center justify-between gap-3"><div><h2 className="text-lg font-semibold">Fila e processamento</h2><p className="text-sm text-zinc-500">Estado atual dos jobs e do worker.</p></div><Badge value={data.queue_health?.queue_connection ?? 'database'} /></div><div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">{queue.map(([key, value, tone, Icon]) => <MiniStat key={key} label={labelFor(key)} value={value} tone={tone} icon={Icon} />)}</div><p className="mt-4 rounded bg-zinc-100 px-3 py-2 text-sm text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{data.queue_health?.recommendation}</p></Card>
      <Card><div className="flex items-center justify-between"><div><h2 className="text-lg font-semibold">Progresso geral</h2><p className="text-sm text-zinc-500">Itens processados nos jobs ativos/concluidos.</p></div><span className="text-2xl font-semibold">{data.overall_progress ?? 0}%</span></div><div className="mt-5 h-3 rounded bg-zinc-100 dark:bg-zinc-800"><div className="h-3 rounded bg-emerald-500" style={{ width: `${data.overall_progress ?? 0}%` }} /></div><div className="mt-5 grid grid-cols-2 gap-3 text-sm"><div><p className="text-zinc-500">Pendentes</p><p className="font-semibold">{cards.pending_sync_jobs ?? 0}</p></div><div><p className="text-zinc-500">Alertas</p><p className="font-semibold">{cards.open_alerts ?? 0}</p></div><div><p className="text-zinc-500">Rate limit</p><p className="font-semibold">{cards.status_429_today ?? 0}</p></div><div><p className="text-zinc-500">Schedules</p><p className="font-semibold">{cards.active_schedules ?? 0}</p></div></div></Card>
    </div>
    {(data.alerts ?? []).length > 0 && <Card><h2 className="mb-3 text-lg font-semibold">Alertas recentes</h2><div className="space-y-2">{data.alerts.map((alert: any) => <div className="flex items-center justify-between rounded border border-zinc-200 p-3 text-sm dark:border-zinc-800" key={alert.id}><span className="font-medium">{alert.title}</span><Badge value={alert.severity} /></div>)}</div></Card>}
    <Card><div className="mb-4"><h2 className="text-lg font-semibold">{t('providerHealth')}</h2><p className="text-sm text-zinc-500">Latencia, erros e limites dos providers internos.</p></div>{health.length === 0 ? <Empty>{t('emptyErrors')}</Empty> : <Table rows={health} columns={['name', 'status', 'active_keys_count', 'requests_today', 'errors_today', 'rate_limit_hits_today', 'average_response_time_ms']} />}</Card>
    <Card><div className="mb-4"><h2 className="text-lg font-semibold">{t('coverage')}</h2><p className="text-sm text-zinc-500">Resumo da base esportiva disponivel na API.</p></div><div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">{Object.entries(coverage?.summary ?? {}).map(([key, value]) => <div className="rounded border border-zinc-200 p-3 dark:border-zinc-800" key={key}><p className="text-xs text-zinc-500">{labelFor(key)}</p><p className="text-xl font-semibold">{String(value)}</p></div>)}</div><div className="mt-4 overflow-auto"><table className="w-full min-w-[760px] text-left text-sm"><thead><tr className="border-b dark:border-zinc-800"><th className="py-2">Liga</th><th>Temporada</th><th>Partidas</th><th>Finalizadas</th><th>Agendadas</th><th>Stats</th><th>Ultimo sync</th></tr></thead><tbody>{(coverage?.rows ?? []).map((row: any, index: number) => <tr className="border-b border-zinc-100 dark:border-zinc-800" key={index}><td className="py-2 font-medium">{row.league?.name}</td><td>{row.season?.year}</td><td>{row.matches_total}</td><td>{row.matches_finished}</td><td>{row.matches_scheduled}</td><td>{row.statistics_count}</td><td>{row.last_synced_at ?? '-'}</td></tr>)}</tbody></table></div></Card>
  </div>;
}

function Table({ rows, columns }: { rows: any[]; columns: string[] }) {
  const valueFor = (row: any, column: string) => {
    const value = row[column];
    if (typeof value === 'boolean') return <Badge value={value ? 'active' : 'inactive'} />;
    if (value && typeof value === 'object') return value.name ?? value.code ?? value.slug ?? '-';
    return String(value ?? '-');
  };
  return <div className="overflow-auto"><table className="w-full min-w-[720px] text-left text-sm"><thead><tr className="border-b dark:border-zinc-800">{columns.map(c => <th className="py-2" key={c}>{c}</th>)}</tr></thead><tbody>{rows.map((row, i) => <tr className="border-b border-zinc-100 dark:border-zinc-800" key={row.id ?? i}>{columns.map(c => <td className="py-2" key={c}>{valueFor(row, c)}</td>)}</tr>)}</tbody></table></div>;
}

function Providers({ t }: { t: (key: string) => string }) {
  const [items, setItems] = useState<Provider[]>();
  const load = () => api<Provider[]>('/admin/api/providers').then(setItems);
  useEffect(() => { load(); }, []);
  if (!items) return <Loader />;
  return <div className="grid gap-4 lg:grid-cols-2">{items.map(provider => <Card key={provider.id}><div className="flex items-start justify-between gap-3"><div><h2 className="text-lg font-semibold">{provider.name}</h2><p className="text-sm text-zinc-500">{provider.slug}</p></div><Badge value={provider.status} /></div><div className="mt-4 space-y-2 text-sm">{provider.base_url && <p><span className="text-zinc-500">{t('baseUrl')}:</span> {provider.base_url}</p>}<div className="flex flex-wrap gap-2">{provider.website_url && <a className="inline-flex items-center gap-1 rounded border px-2 py-1 dark:border-zinc-700" href={provider.website_url} target="_blank"><LinkIcon className="h-4 w-4" />{t('website')}</a>}{provider.docs_url && <a className="inline-flex items-center gap-1 rounded border px-2 py-1 dark:border-zinc-700" href={provider.docs_url} target="_blank"><LinkIcon className="h-4 w-4" />{t('docs')}</a>}</div>{provider.last_error && <p className="text-rose-600">{provider.last_error}</p>}</div><div className="mt-4 flex flex-wrap gap-2"><button className="rounded border px-3 py-2 text-sm dark:border-zinc-700" onClick={async () => { await api(`/admin/api/providers/${provider.id}`, { method: 'PATCH', body: JSON.stringify({ ...provider, is_active: !provider.is_active, status: !provider.is_active ? 'active' : 'inactive' }) }); load(); }}>{provider.is_active ? t('inactive') : t('active')}</button><button className="rounded border px-3 py-2 text-sm dark:border-zinc-700" onClick={() => api(`/admin/api/providers/${provider.id}/test`, { method: 'POST' }).then(load)}>{t('test')}</button><button className="rounded bg-emerald-600 px-3 py-2 text-sm font-semibold text-white" onClick={() => api(`/admin/api/providers/${provider.id}/full-sync`, { method: 'POST', body: JSON.stringify({ request_interval_seconds: 60 }) }).then(() => alert('Full sync agendado. Acompanhe em Sync Jobs.'))}>Full sync</button></div></Card>)}</div>;
}

function Keys({ t }: { t: (key: string) => string }) {
  const [keys, setKeys] = useState<ApiKey[]>([]);
  const [providers, setProviders] = useState<Provider[]>([]);
  const [form, setForm] = useState({ api_provider_id: '', name: '', api_key: '' });
  const load = () => { api<ApiKey[]>('/admin/api/provider-keys').then(setKeys); api<Provider[]>('/admin/api/providers').then(setProviders); };
  useEffect(load, []);
  return <div className="space-y-4"><Card><h2 className="mb-3 font-semibold">{t('createKey')}</h2><form className="grid gap-3 md:grid-cols-4" onSubmit={async e => { e.preventDefault(); await api('/admin/api/provider-keys', { method: 'POST', body: JSON.stringify({ ...form, api_provider_id: Number(form.api_provider_id) }) }); setForm({ api_provider_id: '', name: '', api_key: '' }); load(); }}><select className="rounded border bg-transparent px-3 py-2 dark:border-zinc-700" value={form.api_provider_id} onChange={e => setForm({ ...form, api_provider_id: e.target.value })}><option value="">Provider</option>{providers.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}</select><input className="rounded border bg-transparent px-3 py-2 dark:border-zinc-700" placeholder="Name" value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} /><input className="rounded border bg-transparent px-3 py-2 dark:border-zinc-700" placeholder="API key" value={form.api_key} onChange={e => setForm({ ...form, api_key: e.target.value })} /><button className="rounded bg-emerald-600 px-3 py-2 text-white">{t('save')}</button></form></Card><div className="grid gap-3 lg:grid-cols-2">{keys.map(key => <Card key={key.id}><div className="flex justify-between"><div><h3 className="font-semibold">{key.name}</h3><p className="text-sm text-zinc-500">{key.provider?.name} - {key.key_hint}</p></div><Badge value={key.is_active ? 'active' : 'inactive'} /></div></Card>)}</div></div>;
}

function SyncJobs({ t, openJob }: { t: (key: string) => string; openJob: (id: number | null) => void }) {
  const [jobs, setJobs] = useState<SyncJob[]>();
  const [providers, setProviders] = useState<Provider[]>([]);
  const [form, setForm] = useState({ api_provider_id: '', type: 'sync_leagues', config: '{}', is_incremental: false });
  const [configError, setConfigError] = useState('');
  const load = () => { api<any>('/admin/api/sync-jobs').then(r => setJobs(r.data ?? r)); api<Provider[]>('/admin/api/providers').then(setProviders); };
  useEffect(load, []);
  const examples = [['Football-Data matches', { competition_code: 'BSA', season: 2026 }], ['API-Football matches', { league_id: 71, season: 2024, timezone: 'America/Sao_Paulo' }], ['API-Football teams', { league_id: 71, season: 2024 }], ['API-Football standings', { league_id: 71, season: 2024 }], ['API-Football statistics', { fixture_id: 123456 }]] as const;
  return <div className="space-y-4"><a className="inline-flex rounded border px-3 py-2 text-sm dark:border-zinc-700" href="/admin/api/sync-jobs/export">Export CSV</a><Card><h2 className="mb-3 font-semibold">{t('createSync')}</h2><form className="grid gap-3" onSubmit={async e => { e.preventDefault(); setConfigError(''); let config = {}; try { config = form.config.trim() ? JSON.parse(form.config) : {}; } catch { setConfigError('Invalid JSON config.'); return; } try { await api('/admin/api/sync-jobs', { method: 'POST', body: JSON.stringify({ api_provider_id: Number(form.api_provider_id), type: form.type, config, is_incremental: form.is_incremental }) }); load(); } catch (err) { setConfigError(err instanceof Error ? err.message : 'Request failed'); } }}><div className="grid gap-3 md:grid-cols-4"><select className="rounded border bg-transparent px-3 py-2 dark:border-zinc-700" value={form.api_provider_id} onChange={e => setForm({ ...form, api_provider_id: e.target.value })}><option value="">Provider</option>{providers.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}</select><select className="rounded border bg-transparent px-3 py-2 dark:border-zinc-700" value={form.type} onChange={e => setForm({ ...form, type: e.target.value })}>{['sync_leagues', 'sync_teams', 'sync_matches', 'sync_standings', 'sync_match_statistics'].map(type => <option key={type}>{type}</option>)}</select><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.is_incremental} onChange={e => setForm({ ...form, is_incremental: e.target.checked })} /> Incremental</label><button className="rounded bg-emerald-600 px-3 py-2 text-white">{t('save')}</button></div><textarea className="min-h-28 rounded border bg-transparent px-3 py-2 font-mono text-sm dark:border-zinc-700" value={form.config} onChange={e => setForm({ ...form, config: e.target.value })} />{configError && <p className="text-sm text-rose-600">{configError}</p>}<div className="flex flex-wrap gap-2">{examples.map(([label, value]) => <button type="button" className="rounded border px-2 py-1 text-xs dark:border-zinc-700" key={label} onClick={() => setForm({ ...form, config: JSON.stringify(value, null, 2), type: label.includes('teams') ? 'sync_teams' : label.includes('standings') ? 'sync_standings' : label.includes('statistics') ? 'sync_match_statistics' : 'sync_matches' })}>{label}</button>)}</div></form></Card>{!jobs ? <Loader /> : jobs.length === 0 ? <Empty>{t('emptyJobs')}</Empty> : jobs.map(job => <Card key={job.id}><div className="flex items-start justify-between"><div><h3 className="font-semibold">#{job.id} {job.type}</h3><p className="text-sm text-zinc-500">{job.provider?.name}</p><div className="mt-2 flex gap-2"><Badge value={job.source ?? 'manual'} />{job.is_incremental && <Badge value="incremental" />}</div></div><Badge value={job.status} /></div><div className="mt-3 grid gap-2 text-sm md:grid-cols-5"><span>{t('progress')}: {Number(job.progress_percent)}%</span><span>Processed: {job.processed_items ?? 0}/{job.total_items ?? 0}</span><span>Created: {job.created_items ?? 0}</span><span>Updated: {job.updated_items ?? 0}</span><span>Failed: {job.failed_items ?? 0}</span></div><div className="mt-3 h-2 rounded bg-zinc-100 dark:bg-zinc-800"><div className="h-2 rounded bg-sky-500" style={{ width: `${Number(job.progress_percent)}%` }} /></div>{job.last_error && <p className="mt-3 text-sm text-rose-600">{job.last_error}</p>}<div className="mt-3 flex flex-wrap gap-2"><button className="inline-flex items-center gap-1 rounded border px-3 py-2 text-sm dark:border-zinc-700" onClick={() => openJob(job.id)}><Eye className="h-4 w-4" />{t('jobDetail')}</button><button className="inline-flex items-center gap-1 rounded border px-3 py-2 text-sm dark:border-zinc-700" onClick={() => api(`/admin/api/sync-jobs/${job.id}/run`, { method: 'POST' }).then(load)}><Play className="h-4 w-4" />{t('run')}</button><button className="inline-flex items-center gap-1 rounded border px-3 py-2 text-sm dark:border-zinc-700" onClick={() => api(`/admin/api/sync-jobs/${job.id}/rerun`, { method: 'POST' }).then(load)}><RefreshCw className="h-4 w-4" />{t('rerun')}</button><button className="inline-flex items-center gap-1 rounded border px-3 py-2 text-sm dark:border-zinc-700" onClick={() => api(`/admin/api/sync-jobs/${job.id}/cancel`, { method: 'POST' }).then(load)}><XCircle className="h-4 w-4" />{t('cancel')}</button></div></Card>)}</div>;
}

function SyncJobDetail({ id, t, back }: { id: number; t: (key: string) => string; back: () => void }) {
  const [data, setData] = useState<any>();
  const [selected, setSelected] = useState<SyncItem | null>(null);
  const load = () => api(`/admin/api/sync-jobs/${id}`).then(setData);
  useEffect(() => { load(); }, [id]);
  if (!data) return <Loader />;
  const job: SyncJob = data.job;
  const cards = { total_items: job.total_items, processed_items: job.processed_items, created_items: job.created_items, updated_items: job.updated_items, failed_items: job.failed_items, progress_percent: job.progress_percent, requests: data.summary.requests_count, request_errors: data.summary.request_errors_count, avg_item_seconds: data.summary.average_duration_per_item_seconds };
  return <div className="space-y-4"><div className="flex flex-wrap items-center justify-between gap-3"><button className="inline-flex items-center gap-1 rounded border px-3 py-2 text-sm dark:border-zinc-700" onClick={back}><ArrowLeft className="h-4 w-4" />{t('back')}</button><div className="flex gap-2"><a className="rounded border px-3 py-2 text-sm dark:border-zinc-700" href={`/admin/api/sync-jobs/${id}/items/export`}>Export items</a><button className="inline-flex items-center gap-1 rounded border px-3 py-2 text-sm dark:border-zinc-700" onClick={() => api(`/admin/api/sync-jobs/${id}/rerun`, { method: 'POST' }).then(load)}><RefreshCw className="h-4 w-4" />{t('rerun')}</button><button className="inline-flex items-center gap-1 rounded border px-3 py-2 text-sm dark:border-zinc-700" onClick={() => api(`/admin/api/sync-jobs/${id}/cancel`, { method: 'POST' }).then(load)}><XCircle className="h-4 w-4" />{t('cancel')}</button></div></div><Card><div className="flex flex-wrap items-start justify-between gap-3"><div><h2 className="text-xl font-semibold">#{job.id} {job.type}</h2><p className="text-sm text-zinc-500">{job.provider?.name} / {job.league?.name ?? '-'} / {job.season?.year ?? '-'}</p><p className="mt-2 text-sm text-zinc-500">Started {job.started_at ?? '-'} - Finished {job.finished_at ?? '-'} - Duration {data.summary.duration_seconds ?? '-'}s</p>{job.cancel_requested_at && <p className="mt-2 text-sm text-amber-600">Cancellation requested at {job.cancel_requested_at}</p>}{job.last_error && <p className="mt-2 text-sm text-rose-600">{job.last_error}</p>}</div><div className="space-y-2 text-right"><Badge value={job.status} />{job.is_incremental && <Badge value="incremental" />}</div></div></Card><div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">{Object.entries(cards).map(([key, value]) => <Card key={key}><p className="text-xs text-zinc-500">{key}</p><p className="mt-1 text-xl font-semibold">{String(value ?? 0)}</p></Card>)}</div><div className="grid gap-4 lg:grid-cols-2"><Card><h3 className="mb-3 font-semibold">Config</h3><JsonBlock value={job.config} t={t} /></Card><Card><h3 className="mb-3 font-semibold">Result</h3><JsonBlock value={job.result} t={t} /></Card></div><Card><h3 className="mb-3 font-semibold">{t('syncItems')}</h3>{(data.items.data ?? []).length === 0 ? <Empty>{t('emptyJobs')}</Empty> : <div className="overflow-auto"><table className="w-full min-w-[900px] text-left text-sm"><thead><tr className="border-b dark:border-zinc-800"><th className="py-2">Status</th><th>Entity</th><th>ID</th><th>External</th><th>Action</th><th>Error</th><th>Created</th><th></th></tr></thead><tbody>{data.items.data.map((item: SyncItem) => <tr className="border-b border-zinc-100 dark:border-zinc-800" key={item.id}><td className="py-2"><Badge value={item.status} /></td><td>{item.entity_type}</td><td>{item.entity_id}</td><td>{item.external_id}</td><td>{item.action && <Badge value={item.action} />}</td><td className="max-w-xs truncate text-rose-600">{item.error_message}</td><td>{item.created_at}</td><td><button className="rounded border p-2 dark:border-zinc-700" onClick={() => setSelected(item)}><Eye className="h-4 w-4" /></button></td></tr>)}</tbody></table></div>}</Card><Card><h3 className="mb-3 font-semibold">{t('requestLogs')}</h3><RequestLogTable rows={data.request_logs.data ?? []} t={t} /></Card>{selected && <div className="fixed inset-0 z-20 bg-black/40 p-4" onClick={() => setSelected(null)}><div className="ml-auto h-full max-w-2xl overflow-auto rounded-lg bg-white p-4 dark:bg-zinc-900" onClick={e => e.stopPropagation()}><button className="mb-3 rounded border px-3 py-2 text-sm dark:border-zinc-700" onClick={() => setSelected(null)}>Close</button><JsonBlock value={selected} t={t} /></div></div>}</div>;
}

function RequestLogTable({ rows, t }: { rows: RequestLog[]; t: (key: string) => string }) {
  const [selected, setSelected] = useState<RequestLog | null>(null);
  return <>{rows.length === 0 ? <Empty>{t('emptyLogs')}</Empty> : <div className="overflow-auto"><table className="w-full min-w-[980px] text-left text-sm"><thead><tr className="border-b dark:border-zinc-800"><th className="py-2">Provider</th><th>Method</th><th>Endpoint</th><th>Status</th><th>Success</th><th>Duration</th><th>Time</th><th>Error</th><th></th></tr></thead><tbody>{rows.map(log => <tr className="border-b border-zinc-100 dark:border-zinc-800" key={log.id}><td className="py-2">{log.provider?.name}</td><td>{log.method}</td><td className="max-w-xs truncate">{log.endpoint}</td><td>{log.status_code}</td><td><Badge value={log.success ? 'completed' : 'failed'} /></td><td>{log.duration_ms}ms</td><td>{log.requested_at}</td><td className="max-w-xs truncate text-rose-600">{log.error_message}</td><td><button className="rounded border p-2 dark:border-zinc-700" onClick={() => setSelected(log)}><Eye className="h-4 w-4" /></button></td></tr>)}</tbody></table></div>}{selected && <div className="fixed inset-0 z-20 bg-black/40 p-4" onClick={() => setSelected(null)}><div className="ml-auto h-full max-w-2xl overflow-auto rounded-lg bg-white p-4 dark:bg-zinc-900" onClick={e => e.stopPropagation()}><button className="mb-3 rounded border px-3 py-2 text-sm dark:border-zinc-700" onClick={() => setSelected(null)}>Close</button><JsonBlock value={selected} t={t} /></div></div>}</>;
}

function Logs({ t }: { t: (key: string) => string }) {
  const [data, setData] = useState<any>();
  const [filters, setFilters] = useState({ provider: '', success: '', status_code: '', sync_job_id: '', endpoint: '' });
  const load = () => api(`/admin/api/request-logs?${qs(filters)}`).then(setData);
  useEffect(() => { load(); }, []);
  return <div className="space-y-4"><a className="inline-flex rounded border px-3 py-2 text-sm dark:border-zinc-700" href={`/admin/api/request-logs/export?${qs(filters)}`}>Export CSV</a><Card><div className="grid gap-3 md:grid-cols-6">{Object.entries(filters).map(([key, value]) => <input key={key} className="rounded border bg-transparent px-3 py-2 text-sm dark:border-zinc-700" placeholder={key} value={value} onChange={e => setFilters({ ...filters, [key]: e.target.value })} />)}<button className="rounded bg-emerald-600 px-3 py-2 text-white" onClick={load}>Filter</button></div></Card>{!data ? <Loader /> : <><div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">{Object.entries(data.cards ?? {}).map(([key, value]) => <Card key={key}><p className="text-xs text-zinc-500">{key}</p><p className="mt-1 text-lg font-semibold">{String(value ?? '-')}</p></Card>)}</div><Card><RequestLogTable rows={data.data?.data ?? []} t={t} /></Card></>}</div>;
}

function Schedules({ t, openJob }: { t: (key: string) => string; openJob: (id: number | null) => void }) {
  const [data, setData] = useState<any>();
  const [providers, setProviders] = useState<Provider[]>([]);
  const [form, setForm] = useState({ name: '', api_provider_id: '', type: 'sync_matches', frequency: 'daily', config: '{\n  "league_id": 71,\n  "season": 2024,\n  "timezone": "America/Sao_Paulo",\n  "max_pages": 10\n}', is_active: true });
  const [error, setError] = useState('');
  const load = () => { api('/admin/api/schedules').then(setData); api<Provider[]>('/admin/api/providers').then(setProviders); };
  useEffect(() => { load(); }, []);
  const rows = data?.data ?? [];
  return <div className="space-y-4"><Card><h2 className="mb-3 font-semibold">Sync Schedules</h2><form className="grid gap-3" onSubmit={async e => { e.preventDefault(); setError(''); let config = {}; try { config = form.config.trim() ? JSON.parse(form.config) : {}; } catch { setError('Invalid JSON config.'); return; } await api('/admin/api/schedules', { method: 'POST', body: JSON.stringify({ ...form, api_provider_id: Number(form.api_provider_id), config }) }); load(); }}><div className="grid gap-3 md:grid-cols-5"><input className="rounded border bg-transparent px-3 py-2 dark:border-zinc-700" placeholder="Name" value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} /><select className="rounded border bg-transparent px-3 py-2 dark:border-zinc-700" value={form.api_provider_id} onChange={e => setForm({ ...form, api_provider_id: e.target.value })}><option value="">Provider</option>{providers.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}</select><select className="rounded border bg-transparent px-3 py-2 dark:border-zinc-700" value={form.type} onChange={e => setForm({ ...form, type: e.target.value })}>{['sync_leagues', 'sync_teams', 'sync_matches', 'sync_standings', 'sync_match_statistics'].map(type => <option key={type}>{type}</option>)}</select><select className="rounded border bg-transparent px-3 py-2 dark:border-zinc-700" value={form.frequency} onChange={e => setForm({ ...form, frequency: e.target.value })}>{['hourly', 'every_6_hours', 'every_12_hours', 'daily', 'weekly'].map(f => <option key={f}>{f}</option>)}</select><button className="rounded bg-emerald-600 px-3 py-2 text-white">{t('save')}</button></div><textarea className="min-h-28 rounded border bg-transparent px-3 py-2 font-mono text-sm dark:border-zinc-700" value={form.config} onChange={e => setForm({ ...form, config: e.target.value })} />{error && <p className="text-sm text-rose-600">{error}</p>}<div className="flex flex-wrap gap-2"><button type="button" className="rounded border px-2 py-1 text-xs dark:border-zinc-700" onClick={() => setForm({ ...form, type: 'sync_matches', config: JSON.stringify({ competition_code: 'BSA', season: 2026 }, null, 2) })}>Football-Data matches</button><button type="button" className="rounded border px-2 py-1 text-xs dark:border-zinc-700" onClick={() => setForm({ ...form, type: 'sync_matches', config: JSON.stringify({ league_id: 71, season: 2024, timezone: 'America/Sao_Paulo', max_pages: 10 }, null, 2) })}>API-Football matches</button><button type="button" className="rounded border px-2 py-1 text-xs dark:border-zinc-700" onClick={() => setForm({ ...form, type: 'sync_standings', config: JSON.stringify({ league_id: 71, season: 2024 }, null, 2) })}>API-Football standings</button></div></form></Card>{!data ? <Loader /> : rows.length === 0 ? <Empty>No schedules found</Empty> : <div className="grid gap-3">{rows.map((schedule: any) => <Card key={schedule.id}><div className="flex flex-wrap items-start justify-between gap-3"><div><h3 className="font-semibold">{schedule.name}</h3><p className="text-sm text-zinc-500">{schedule.provider?.name} / {schedule.type} / {schedule.frequency}</p><p className="mt-1 text-xs text-zinc-500">Next {schedule.next_run_at ?? '-'} - Last {schedule.last_run_at ?? '-'}</p>{schedule.last_error && <p className="mt-2 text-sm text-rose-600">{schedule.last_error}</p>}</div><Badge value={schedule.is_active ? 'active' : 'inactive'} /></div><div className="mt-3 flex flex-wrap gap-2"><button className="rounded border px-3 py-2 text-sm dark:border-zinc-700" onClick={() => api(`/admin/api/schedules/${schedule.id}`, { method: 'PATCH', body: JSON.stringify({ is_active: !schedule.is_active }) }).then(load)}>{schedule.is_active ? t('inactive') : t('active')}</button><button className="rounded border px-3 py-2 text-sm dark:border-zinc-700" onClick={() => api(`/admin/api/schedules/${schedule.id}/run`, { method: 'POST' }).then(load)}>{t('run')}</button>{schedule.last_sync_job_id && <button className="rounded border px-3 py-2 text-sm dark:border-zinc-700" onClick={() => openJob(schedule.last_sync_job_id)}>{t('jobDetail')}</button>}</div></Card>)}</div>}</div>;
}

const blankPlan = (): Plan => ({ name: 'Free', slug: 'free', description: '', is_active: true, is_default: false, allow_all: false, requests_per_minute: 10, max_active_api_keys: 3, access_rules: [] });

function Plans({ t }: { t: (key: string) => string }) {
  const [plans, setPlans] = useState<Plan[]>();
  const [options, setOptions] = useState<any>();
  const [form, setForm] = useState<Plan>(blankPlan());
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const load = () => { api<Plan[]>('/admin/api/plans').then(setPlans); api('/admin/api/plan-options').then(setOptions); };
  useEffect(() => { load(); }, []);
  const updateRule = (index: number, patch: Partial<PlanRule>) => setForm({ ...form, access_rules: form.access_rules.map((rule, current) => current === index ? { ...rule, ...patch } : rule) });
  const cleanRule = (rule: PlanRule) => ({ scope_type: rule.scope_type, region: rule.region || null, country_id: rule.country_id ? Number(rule.country_id) : null, league_id: rule.league_id ? Number(rule.league_id) : null, season_id: rule.season_id ? Number(rule.season_id) : null });
  if (!plans || !options) return <Loader />;
  return <div className="grid gap-4 xl:grid-cols-[.8fr_1.2fr]">
    <Card><div className="mb-4 flex items-center justify-between gap-3"><div><h2 className="text-lg font-semibold">Planos</h2><p className="text-sm text-zinc-500">Controle o escopo de dados por produto.</p></div><button className="rounded border px-3 py-2 text-sm dark:border-zinc-700" onClick={() => setForm(blankPlan())}>Novo</button></div><div className="space-y-2">{plans.map(plan => <button key={plan.id} className={`w-full rounded border p-3 text-left text-sm dark:border-zinc-800 ${form.id === plan.id ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-950' : ''}`} onClick={() => setForm({ ...plan, access_rules: plan.access_rules ?? [] })}><div className="flex items-center justify-between gap-2"><span className="font-semibold">{plan.name}</span><div className="flex gap-1">{plan.is_default && <Badge value="default" />}{plan.allow_all && <Badge value="all" />}</div></div><p className="mt-1 text-xs text-zinc-500">{plan.users_count ?? 0} usuarios / {plan.access_rules?.length ?? 0} regras</p></button>)}</div></Card>
    <Card><form className="space-y-4" onSubmit={async e => { e.preventDefault(); setError(''); setMessage(''); const payload = { ...form, requests_per_minute: Number(form.requests_per_minute), max_active_api_keys: Number(form.max_active_api_keys), access_rules: form.allow_all ? [] : form.access_rules.map(cleanRule) }; try { const saved = await api<Plan>(form.id ? `/admin/api/plans/${form.id}` : '/admin/api/plans', { method: form.id ? 'PATCH' : 'POST', body: JSON.stringify(payload) }); setForm({ ...saved, access_rules: saved.access_rules ?? [] }); setMessage('Plano salvo.'); load(); } catch (err) { setError(err instanceof Error ? err.message : 'Save failed'); } }}>
      <div className="grid gap-3 md:grid-cols-2"><input className="rounded border px-3 py-2 dark:border-zinc-700" value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} placeholder="Nome" /><input className="rounded border px-3 py-2 dark:border-zinc-700" value={form.slug} onChange={e => setForm({ ...form, slug: e.target.value })} placeholder="slug" /></div>
      <textarea className="min-h-20 w-full rounded border px-3 py-2 dark:border-zinc-700" value={form.description ?? ''} onChange={e => setForm({ ...form, description: e.target.value })} placeholder="Descricao" />
      <div className="grid gap-3 md:grid-cols-4"><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.is_active} onChange={e => setForm({ ...form, is_active: e.target.checked })} /> Ativo</label><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.is_default} onChange={e => setForm({ ...form, is_default: e.target.checked })} /> Padrao</label><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.allow_all} onChange={e => setForm({ ...form, allow_all: e.target.checked })} /> Acesso total</label></div>
      <div className="grid gap-3 md:grid-cols-2"><label className="text-sm text-zinc-500">Req/min<input className="mt-1 w-full rounded border px-3 py-2 text-zinc-950 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-50" type="number" min="1" value={form.requests_per_minute} onChange={e => setForm({ ...form, requests_per_minute: Number(e.target.value) })} /></label><label className="text-sm text-zinc-500">Chaves ativas<input className="mt-1 w-full rounded border px-3 py-2 text-zinc-950 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-50" type="number" min="1" value={form.max_active_api_keys} onChange={e => setForm({ ...form, max_active_api_keys: Number(e.target.value) })} /></label></div>
      {!form.allow_all && <div className="space-y-3"><div className="flex flex-wrap items-center justify-between gap-3"><h3 className="font-semibold">Regras de acesso</h3><div className="flex flex-wrap gap-2">{['region', 'country', 'league', 'season'].map(scope => <button key={scope} type="button" className="rounded border px-2 py-1 text-xs dark:border-zinc-700" onClick={() => setForm({ ...form, access_rules: [...form.access_rules, { scope_type: scope, region: scope === 'region' ? 'americas' : null }] })}>+ {scope}</button>)}</div></div>{form.access_rules.length === 0 && <Empty>Sem regras: plano fica sem restricao de liga ate voce adicionar uma regra.</Empty>}{form.access_rules.map((rule, index) => <div className="grid gap-2 rounded border p-3 dark:border-zinc-800 md:grid-cols-[140px_1fr_auto]" key={index}><select className="rounded border bg-transparent px-3 py-2 dark:border-zinc-700" value={rule.scope_type} onChange={e => updateRule(index, { scope_type: e.target.value, region: e.target.value === 'region' ? 'americas' : null, country_id: '', league_id: '', season_id: '' })}>{['region', 'country', 'league', 'season'].map(scope => <option key={scope}>{scope}</option>)}</select>{rule.scope_type === 'region' && <select className="rounded border bg-transparent px-3 py-2 dark:border-zinc-700" value={rule.region ?? 'americas'} onChange={e => updateRule(index, { region: e.target.value })}><option value="americas">Americas</option></select>}{rule.scope_type === 'country' && <select className="rounded border bg-transparent px-3 py-2 dark:border-zinc-700" value={rule.country_id ?? ''} onChange={e => updateRule(index, { country_id: e.target.value })}><option value="">Pais</option>{options.countries.map((country: any) => <option key={country.id} value={country.id}>{country.name} ({country.code})</option>)}</select>}{rule.scope_type === 'league' && <select className="rounded border bg-transparent px-3 py-2 dark:border-zinc-700" value={rule.league_id ?? ''} onChange={e => updateRule(index, { league_id: e.target.value })}><option value="">Liga</option>{options.leagues.map((league: any) => <option key={league.id} value={league.id}>{league.name} {league.country?.code ? `(${league.country.code})` : ''}</option>)}</select>}{rule.scope_type === 'season' && <select className="rounded border bg-transparent px-3 py-2 dark:border-zinc-700" value={rule.season_id ?? ''} onChange={e => updateRule(index, { season_id: e.target.value })}><option value="">Temporada</option>{options.seasons.map((season: any) => <option key={season.id} value={season.id}>{season.league?.name} - {season.year}</option>)}</select>}<button type="button" className="rounded border px-3 py-2 text-sm dark:border-zinc-700" onClick={() => setForm({ ...form, access_rules: form.access_rules.filter((_, current) => current !== index) })}>Remover</button></div>)}</div>}
      {message && <p className="text-sm text-emerald-600">{message}</p>}{error && <p className="text-sm text-rose-600">{error}</p>}<button className="rounded bg-emerald-600 px-4 py-2 font-semibold text-white">{t('save')}</button>
    </form></Card>
  </div>;
}

function AdminUsers() {
  const [overview, setOverview] = useState<any>();
  const [tokens, setTokens] = useState<any>();
  const [usage, setUsage] = useState<any>();
  const [plans, setPlans] = useState<Plan[]>([]);
  const [filters, setFilters] = useState({ search: '', status: '', endpoint: '', status_code: '' });
  const load = () => {
    api(`/admin/api/users-overview?${qs({ search: filters.search })}`).then(setOverview);
    api(`/admin/api/user-api-tokens?${qs({ status: filters.status })}`).then(setTokens);
    api(`/admin/api/user-api-usage?${qs({ endpoint: filters.endpoint, status_code: filters.status_code })}`).then(setUsage);
    api<Plan[]>('/admin/api/plans').then(setPlans);
  };
  useEffect(() => { load(); }, []);
  if (!overview || !tokens || !usage) return <Loader />;
  return <div className="space-y-4">
    <Card><div className="grid gap-3 md:grid-cols-5"><input className="rounded border bg-transparent px-3 py-2 text-sm dark:border-zinc-700" placeholder="Buscar usuario" value={filters.search} onChange={e => setFilters({ ...filters, search: e.target.value })} /><select className="rounded border bg-transparent px-3 py-2 text-sm dark:border-zinc-700" value={filters.status} onChange={e => setFilters({ ...filters, status: e.target.value })}><option value="">Todas as chaves</option><option value="active">Ativas</option><option value="revoked">Revogadas</option></select><input className="rounded border bg-transparent px-3 py-2 text-sm dark:border-zinc-700" placeholder="Endpoint" value={filters.endpoint} onChange={e => setFilters({ ...filters, endpoint: e.target.value })} /><input className="rounded border bg-transparent px-3 py-2 text-sm dark:border-zinc-700" placeholder="Status code" value={filters.status_code} onChange={e => setFilters({ ...filters, status_code: e.target.value })} /><button className="rounded bg-emerald-600 px-3 py-2 text-white" onClick={load}>Filtrar</button></div></Card>
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">{Object.entries(overview.cards ?? {}).map(([key, value]) => <Card key={key}><p className="text-xs text-zinc-500">{key.replace(/_/g, ' ')}</p><p className="mt-1 text-xl font-semibold">{String(value ?? 0)}</p></Card>)}</div>
    <Card><h2 className="mb-3 font-semibold">Usuarios</h2><div className="overflow-auto"><table className="w-full min-w-[960px] text-left text-sm"><thead><tr className="border-b dark:border-zinc-800"><th className="py-2">ID</th><th>Nome</th><th>Email</th><th>Plano</th><th>Admin</th><th>Chaves</th><th>Requests hoje</th><th>Total</th></tr></thead><tbody>{(overview.data?.data ?? []).map((user: any) => <tr className="border-b border-zinc-100 dark:border-zinc-800" key={user.id}><td className="py-2">{user.id}</td><td>{user.name}</td><td>{user.email}</td><td><select className="rounded border bg-transparent px-2 py-1 text-sm dark:border-zinc-700" value={user.plan?.id ?? ''} onChange={e => api(`/admin/api/users/${user.id}/plan`, { method: 'PATCH', body: JSON.stringify({ plan_id: e.target.value ? Number(e.target.value) : null }) }).then(load)}><option value="">Padrao</option>{plans.map(plan => <option key={plan.id} value={plan.id}>{plan.name}</option>)}</select></td><td><Badge value={user.is_admin ? 'active' : 'inactive'} /></td><td>{user.active_api_tokens_count}</td><td>{user.requests_today_count}</td><td>{user.requests_total_count}</td></tr>)}</tbody></table></div></Card>
    <Card><h2 className="mb-3 font-semibold">API keys dos usuarios</h2><div className="overflow-auto"><table className="w-full min-w-[900px] text-left text-sm"><thead><tr className="border-b dark:border-zinc-800"><th className="py-2">Usuario</th><th>Chave</th><th>Prefixo</th><th>Status</th><th>Requests</th><th>Ultimo uso</th><th>Criada</th></tr></thead><tbody>{(tokens.data ?? []).map((token: any) => <tr className="border-b border-zinc-100 dark:border-zinc-800" key={token.id}><td className="py-2">{token.user?.email}</td><td>{token.name}</td><td className="font-mono">{token.token_prefix}...</td><td><Badge value={token.revoked_at ? 'revoked' : 'active'} /></td><td>{token.request_logs_count ?? 0}</td><td>{token.last_used_at ?? '-'}</td><td>{token.created_at}</td></tr>)}</tbody></table></div></Card>
    <Card><h2 className="mb-3 font-semibold">Consumo da API</h2><div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">{Object.entries(usage.cards ?? {}).map(([key, value]) => <div key={key}><p className="text-xs text-zinc-500">{key.replace(/_/g, ' ')}</p><p className="text-lg font-semibold">{String(value ?? 0)}</p></div>)}</div><div className="overflow-auto"><table className="w-full min-w-[960px] text-left text-sm"><thead><tr className="border-b dark:border-zinc-800"><th className="py-2">Usuario</th><th>Chave</th><th>Metodo</th><th>Endpoint</th><th>Status</th><th>Duração</th><th>Data</th></tr></thead><tbody>{(usage.data?.data ?? []).map((log: any) => <tr className="border-b border-zinc-100 dark:border-zinc-800" key={log.id}><td className="py-2">{log.user?.email}</td><td>{log.token?.name ?? '-'}</td><td>{log.method}</td><td className="max-w-xs truncate">{log.endpoint}</td><td>{log.status_code}</td><td>{log.duration_ms}ms</td><td>{log.requested_at}</td></tr>)}</tbody></table></div></Card>
  </div>;
}

function EntityPage({ name }: { name: string }) {
  const [rows, setRows] = useState<any[]>();
  useEffect(() => { api<any>(`/admin/api/${name}`).then(r => setRows(r.data ?? r)); }, [name]);
  if (!rows) return <Loader />;
  const columnsByEntity: Record<string, string[]> = {
    sports: ['id', 'name', 'slug', 'is_active', 'updated_at'],
    countries: ['id', 'name', 'code', 'updated_at'],
    leagues: ['id', 'name', 'slug', 'type', 'is_active', 'updated_at'],
    seasons: ['id', 'name', 'year', 'league_id', 'updated_at'],
    teams: ['id', 'name', 'short_name', 'slug', 'venue_name', 'is_active', 'updated_at'],
    matches: ['id', 'starts_at', 'status', 'home_score', 'away_score', 'updated_at'],
  };
  return <Card>{rows.length === 0 ? <Empty>No records found</Empty> : <Table rows={rows} columns={columnsByEntity[name] ?? ['id', 'name', 'updated_at']} />}</Card>;
}

function SettingsPage({ t }: { t: (key: string) => string }) {
  const [settings, setSettings] = useState<HomeSettings>();
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  useEffect(() => { api<HomeSettings>('/admin/api/homepage-settings').then(setSettings); }, []);
  if (!settings) return <Loader />;
  const update = (patch: Partial<HomeSettings>) => setSettings({ ...settings, ...patch });
  const updateFeature = (index: number, patch: Partial<HomeSettings['features'][number]>) => {
    const features = settings.features.map((feature, current) => current === index ? { ...feature, ...patch } : feature);
    update({ features });
  };
  return <div className="space-y-4">
    <Card><div className="flex flex-wrap items-center justify-between gap-3"><div><h2 className="text-lg font-semibold">Homepage</h2><p className="text-sm text-zinc-500">Edite a pagina publica, CTAs e cards comerciais.</p></div><a className="rounded border px-3 py-2 text-sm dark:border-zinc-700" href="/" target="_blank">Preview</a></div></Card>
    <Card><form className="space-y-4" onSubmit={async e => { e.preventDefault(); setError(''); setMessage(''); try { const saved = await api<HomeSettings>('/admin/api/homepage-settings', { method: 'PATCH', body: JSON.stringify(settings) }); setSettings(saved); setMessage('Homepage salva.'); } catch (err) { setError(err instanceof Error ? err.message : 'Save failed'); } }}>
      <div className="grid gap-3 md:grid-cols-2">
        <input className="rounded border px-3 py-2 dark:border-zinc-700" value={settings.brand_name} onChange={e => update({ brand_name: e.target.value })} placeholder="Brand name" />
        <input className="rounded border px-3 py-2 dark:border-zinc-700" value={settings.nav_badge} onChange={e => update({ nav_badge: e.target.value })} placeholder="Badge" />
      </div>
      <input className="w-full rounded border px-3 py-2 dark:border-zinc-700" value={settings.hero_title} onChange={e => update({ hero_title: e.target.value })} placeholder="Hero title" />
      <textarea className="min-h-24 w-full rounded border px-3 py-2 dark:border-zinc-700" value={settings.hero_subtitle} onChange={e => update({ hero_subtitle: e.target.value })} placeholder="Hero subtitle" />
      <input className="w-full rounded border px-3 py-2 dark:border-zinc-700" value={settings.hero_image_url} onChange={e => update({ hero_image_url: e.target.value })} placeholder="Hero image URL" />
      <div className="grid gap-3 md:grid-cols-5">
        <input className="rounded border px-3 py-2 dark:border-zinc-700" value={settings.primary_cta_label} onChange={e => update({ primary_cta_label: e.target.value })} placeholder="Primary CTA" />
        <input className="rounded border px-3 py-2 dark:border-zinc-700" value={settings.primary_cta_url} onChange={e => update({ primary_cta_url: e.target.value })} placeholder="Primary URL" />
        <input className="rounded border px-3 py-2 dark:border-zinc-700" value={settings.secondary_cta_label} onChange={e => update({ secondary_cta_label: e.target.value })} placeholder="Secondary CTA" />
        <input className="rounded border px-3 py-2 dark:border-zinc-700" value={settings.secondary_cta_url} onChange={e => update({ secondary_cta_url: e.target.value })} placeholder="Secondary URL" />
        <input className="rounded border px-3 py-2 dark:border-zinc-700" value={settings.accent_color} onChange={e => update({ accent_color: e.target.value })} placeholder="#16a34a" />
      </div>
      <div className="grid gap-3 lg:grid-cols-3">{settings.features.map((feature, index) => <div className="rounded border p-3 dark:border-zinc-800" key={index}><input className="mb-2 w-full rounded border px-3 py-2 dark:border-zinc-700" value={feature.title} onChange={e => updateFeature(index, { title: e.target.value })} placeholder={`Feature ${index + 1}`} /><textarea className="min-h-24 w-full rounded border px-3 py-2 dark:border-zinc-700" value={feature.description} onChange={e => updateFeature(index, { description: e.target.value })} /></div>)}</div>
      {message && <p className="text-sm text-emerald-600">{message}</p>}{error && <p className="text-sm text-rose-600">{error}</p>}
      <button className="rounded bg-emerald-600 px-4 py-2 font-semibold text-white">{t('save')}</button>
    </form></Card>
  </div>;
}

function AdminLayout({ page, jobId, locale, theme, t, setPage, setJobId, setLocale, setTheme, setLogged }: { page: string; jobId: number | null; locale: Locale; theme: Theme; t: (key: string) => string; setPage: (page: string) => void; setJobId: (id: number | null) => void; setLocale: (locale: Locale) => void; setTheme: (theme: Theme) => void; setLogged: (logged: boolean) => void }) {
  const title = jobId ? t('jobDetail') : pageTitle(page, t);
  const subtitle = jobId ? 'Analise detalhada da execucao, itens e chamadas externas.' : pageSubtitle(page);
  const openPage = (id: string) => { setJobId(null); setPage(id); };
  const logout = () => api('/admin/api/logout', { method: 'POST' }).then(() => setLogged(false));
  return <div className="min-h-screen bg-zinc-100 text-zinc-950 dark:bg-zinc-950 dark:text-zinc-50">
    <aside className="fixed inset-y-0 left-0 hidden w-72 border-r border-zinc-900/10 bg-zinc-950 p-5 text-zinc-100 shadow-xl dark:border-zinc-800 lg:flex lg:flex-col">
      <div className="mb-7 flex items-center gap-3">
        <span className="grid h-10 w-10 place-items-center rounded bg-emerald-500 text-white"><Database className="h-5 w-5" /></span>
        <div>
          <p className="text-base font-semibold">MasterFut Admin</p>
          <p className="text-xs text-zinc-400">Data operations hub</p>
        </div>
      </div>
      <nav className="flex-1 space-y-6 overflow-auto pr-1">
        {navGroups.map(group => <div key={group.label}>
          <p className="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-zinc-500">{group.label}</p>
          <div className="space-y-1">{group.items.map(([id, Icon]) => <button key={id} className={`flex w-full items-center gap-3 rounded px-3 py-2.5 text-left text-sm transition ${page === id && !jobId ? 'bg-emerald-500 text-white shadow-sm' : 'text-zinc-300 hover:bg-zinc-900 hover:text-white'}`} onClick={() => openPage(id)}><Icon className="h-4 w-4" />{t(id)}</button>)}</div>
        </div>)}
      </nav>
      <button className="mt-5 flex w-full items-center gap-3 rounded border border-zinc-800 px-3 py-2.5 text-left text-sm text-zinc-300 hover:bg-zinc-900 hover:text-white" onClick={logout}><LogOut className="h-4 w-4" />{t('logout')}</button>
    </aside>
    <div className="lg:pl-72">
      <header className="sticky top-0 z-10 border-b border-zinc-200 bg-white/90 px-4 py-4 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/90 lg:px-8">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 className="text-xl font-semibold tracking-normal">{title}</h1>
            <p className="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{subtitle}</p>
          </div>
          <div className="flex items-center gap-2">
            <select className="rounded border bg-transparent px-2 py-1 text-sm dark:border-zinc-700" value={locale} onChange={e => setLocale(e.target.value as Locale)}><option value="pt-BR">Portugues</option><option value="en">English</option><option value="es">Espanol</option><option value="zh">Chinese</option></select>
            <button className="rounded border p-2 dark:border-zinc-700" onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}>{theme === 'dark' ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}</button>
            <button className="rounded border p-2 dark:border-zinc-700 lg:hidden" onClick={logout}><LogOut className="h-4 w-4" /></button>
          </div>
        </div>
      </header>
      <main className="p-4 lg:p-8">{jobId && <SyncJobDetail id={jobId} t={t} back={() => setJobId(null)} />}{!jobId && page === 'dashboard' && <Dashboard t={t} />}{!jobId && page === 'users' && <AdminUsers />}{!jobId && page === 'plans' && <Plans t={t} />}{!jobId && page === 'providers' && <Providers t={t} />}{!jobId && page === 'keys' && <Keys t={t} />}{!jobId && page === 'sync' && <SyncJobs t={t} openJob={setJobId} />}{!jobId && page === 'schedules' && <Schedules t={t} openJob={setJobId} />}{!jobId && page === 'logs' && <Logs t={t} />}{!jobId && !['dashboard', 'users', 'plans', 'providers', 'keys', 'sync', 'schedules', 'logs', 'settings'].includes(page) && <EntityPage name={page} />}{!jobId && page === 'settings' && <SettingsPage t={t} />}</main>
    </div>
  </div>;
}

function App() {
  const [locale, setLocale] = useState<Locale>(() => (localStorage.getItem('futia.locale') as Locale) || 'pt-BR');
  const [theme, setTheme] = useState<Theme>(() => {
    const storedTheme = localStorage.getItem('futia.theme');
    return storedTheme === 'dark' || storedTheme === 'light' ? storedTheme : 'light';
  });
  const [page, setPage] = useState('dashboard');
  const [jobId, setJobId] = useState<number | null>(null);
  const [logged, setLogged] = useState<boolean | null>(null);
  const t = useMemo(() => (key: string) => messages[locale][key] ?? key, [locale]);
  useEffect(() => { document.documentElement.classList.toggle('dark', theme === 'dark'); document.documentElement.style.colorScheme = theme; localStorage.setItem('futia.theme', theme); }, [theme]);
  useEffect(() => { localStorage.setItem('futia.locale', locale); }, [locale]);
  useEffect(() => { api('/admin/api/me').then(() => setLogged(true)).catch(() => setLogged(false)); }, []);
  if (logged === null) return <main className="grid min-h-screen place-items-center"><RefreshCw className="h-6 w-6 animate-spin" /></main>;
  if (!logged) return <Login onLogin={() => setLogged(true)} t={t} />;
  return <AdminLayout page={page} jobId={jobId} locale={locale} theme={theme} t={t} setPage={setPage} setJobId={setJobId} setLocale={setLocale} setTheme={setTheme} setLogged={setLogged} />;
}

createRoot(document.getElementById('root')!).render(<App />);

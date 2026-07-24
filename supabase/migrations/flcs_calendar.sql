-- ============================================================
--  FLCS wedstrijdendatabase (kalender)
--  Prefix flcs_ i.p.v. publitool_ : deze tabellen verhuizen later
--  naar de FLCS-toolkit-DB. UUID-sleutels maken die verhuizing
--  een simpele dump + restore, zonder id-conflicten.
-- ============================================================

begin;

-- ------------------------------------------------------------
-- 1. Seizoenen
-- ------------------------------------------------------------
create table if not exists public.flcs_seasons (
  id          uuid primary key default gen_random_uuid(),
  label       text not null,                 -- '2026-2027'
  start_year  integer not null,
  end_year    integer not null,
  is_current  boolean not null default false,
  sort_order  integer not null default 0,
  deleted_at  timestamptz,
  created_at  timestamptz not null default now(),
  updated_at  timestamptz not null default now()
);

create unique index if not exists flcs_seasons_label_uidx
  on public.flcs_seasons (label) where deleted_at is null;

-- Maximaal één seizoen tegelijk als 'huidig'
create unique index if not exists flcs_seasons_one_current_uidx
  on public.flcs_seasons ((true)) where is_current and deleted_at is null;

-- ------------------------------------------------------------
-- 2. Reeksen (Superprestige, X2O Trofee, UCI World Cup, ...)
--    Eén naam die je overschrijft bij hernoemen.
-- ------------------------------------------------------------
create table if not exists public.flcs_series (
  id          uuid primary key default gen_random_uuid(),
  slug        text not null,                 -- 'superprestige'
  name        text not null,                 -- 'Superprestige'
  discipline  text not null default 'veldrijden',
  sort_order  integer not null default 0,
  deleted_at  timestamptz,
  created_at  timestamptz not null default now(),
  updated_at  timestamptz not null default now(),
  constraint flcs_series_discipline_chk
    check (discipline in ('veldrijden','weg','mtb','gravel','piste','andere'))
);

create unique index if not exists flcs_series_slug_uidx
  on public.flcs_series (slug) where deleted_at is null;

-- ------------------------------------------------------------
-- 3. Wedstrijden (tijdloze identiteit: naam + discipline)
-- ------------------------------------------------------------
create table if not exists public.flcs_events (
  id          uuid primary key default gen_random_uuid(),
  slug        text not null,                 -- 'ronde-van-vlaanderen'
  name        text not null,                 -- 'Ronde van Vlaanderen'
  discipline  text not null,
  is_active   boolean not null default true, -- staat niet meer op de kalender
  notes       text,
  deleted_at  timestamptz,
  created_at  timestamptz not null default now(),
  updated_at  timestamptz not null default now(),
  constraint flcs_events_discipline_chk
    check (discipline in ('veldrijden','weg','mtb','gravel','piste','andere'))
);

create unique index if not exists flcs_events_slug_uidx
  on public.flcs_events (slug) where deleted_at is null;

create index if not exists flcs_events_discipline_idx
  on public.flcs_events (discipline) where deleted_at is null;

-- ------------------------------------------------------------
-- 4. Edities: één wedstrijd in één seizoen
--    Gaat hij door? Wanneer? Waar start/finish? Welke reeks?
-- ------------------------------------------------------------
create table if not exists public.flcs_event_editions (
  id                uuid primary key default gen_random_uuid(),
  event_id          uuid not null references public.flcs_events(id)  on delete restrict,
  season_id         uuid not null references public.flcs_seasons(id) on delete restrict,
  series_id         uuid          references public.flcs_series(id)  on delete set null,
  status            text not null default 'planned',
  event_date        date,          -- eerste dag; bij meerdaagse = dag van rit 1
  start_time        time,
  start_location    text,
  finish_location   text,
  notes             text,
  deleted_at        timestamptz,
  created_at        timestamptz not null default now(),
  updated_at        timestamptz not null default now(),
  constraint flcs_editions_status_chk
    check (status in ('planned','confirmed','cancelled'))
);

-- Eén editie per wedstrijd per seizoen
create unique index if not exists flcs_editions_event_season_uidx
  on public.flcs_event_editions (event_id, season_id) where deleted_at is null;

create index if not exists flcs_editions_season_idx
  on public.flcs_event_editions (season_id) where deleted_at is null;
create index if not exists flcs_editions_date_idx
  on public.flcs_event_editions (event_date) where deleted_at is null;
create index if not exists flcs_editions_series_idx
  on public.flcs_event_editions (series_id) where deleted_at is null;

-- ------------------------------------------------------------
-- 5. Ritten (meerdaagse wedstrijden). Leeg bij ééndagswedstrijden.
-- ------------------------------------------------------------
create table if not exists public.flcs_stages (
  id               uuid primary key default gen_random_uuid(),
  edition_id       uuid not null references public.flcs_event_editions(id) on delete cascade,
  stage_number     integer not null,
  name             text,           -- 'Rit 3 - individuele tijdrit'
  stage_date       date,
  start_time       time,
  start_location   text,
  finish_location  text,
  notes            text,
  deleted_at       timestamptz,
  created_at       timestamptz not null default now(),
  updated_at       timestamptz not null default now()
);

create unique index if not exists flcs_stages_edition_number_uidx
  on public.flcs_stages (edition_id, stage_number) where deleted_at is null;

create index if not exists flcs_stages_edition_idx
  on public.flcs_stages (edition_id) where deleted_at is null;

-- ------------------------------------------------------------
-- 6. updated_at triggers (hergebruikt de bestaande helper)
-- ------------------------------------------------------------
drop trigger if exists flcs_seasons_touch  on public.flcs_seasons;
drop trigger if exists flcs_series_touch   on public.flcs_series;
drop trigger if exists flcs_events_touch   on public.flcs_events;
drop trigger if exists flcs_editions_touch on public.flcs_event_editions;
drop trigger if exists flcs_stages_touch   on public.flcs_stages;

create trigger flcs_seasons_touch  before update on public.flcs_seasons
  for each row execute function public.touch_updated_at();
create trigger flcs_series_touch   before update on public.flcs_series
  for each row execute function public.touch_updated_at();
create trigger flcs_events_touch   before update on public.flcs_events
  for each row execute function public.touch_updated_at();
create trigger flcs_editions_touch before update on public.flcs_event_editions
  for each row execute function public.touch_updated_at();
create trigger flcs_stages_touch   before update on public.flcs_stages
  for each row execute function public.touch_updated_at();

-- ------------------------------------------------------------
-- 7. Publieke leesview — dit is het contract voor andere apps.
--    Kolommen bijzetten mag, hernoemen nooit: maak dan _v2.
-- ------------------------------------------------------------
create or replace view public.flcs_calendar_v1 as
select
  ed.id                as edition_id,
  ev.id                as event_id,
  ev.slug              as event_slug,
  ev.name              as event_name,
  ev.discipline        as discipline,
  se.id                as season_id,
  se.label             as season_label,
  sr.id                as series_id,
  sr.name              as series_name,
  ed.status            as status,
  ed.event_date        as event_date,
  coalesce(
    (select max(st.stage_date) from public.flcs_stages st
      where st.edition_id = ed.id and st.deleted_at is null),
    ed.event_date
  )                    as end_date,
  (select count(*) from public.flcs_stages st
    where st.edition_id = ed.id and st.deleted_at is null)
                       as stage_count,
  ed.start_time        as start_time,
  ed.start_location    as start_location,
  ed.finish_location   as finish_location,
  ed.notes             as notes,
  ed.updated_at        as updated_at
from public.flcs_event_editions ed
join public.flcs_events  ev on ev.id = ed.event_id
join public.flcs_seasons se on se.id = ed.season_id
left join public.flcs_series sr on sr.id = ed.series_id
where ed.deleted_at is null
  and ev.deleted_at is null
  and se.deleted_at is null;

-- RLS van de onderliggende tabellen laten gelden
alter view public.flcs_calendar_v1 set (security_invoker = on);

-- ------------------------------------------------------------
-- 8. RLS
--    Lezen: iedereen (ook anon) — de kalender is publieke info en
--    andere apps lezen straks met de publishable key van dit project.
--    Schrijven: Publitool-editors/beheerders.
-- ------------------------------------------------------------
alter table public.flcs_seasons        enable row level security;
alter table public.flcs_series         enable row level security;
alter table public.flcs_events         enable row level security;
alter table public.flcs_event_editions enable row level security;
alter table public.flcs_stages         enable row level security;

drop policy if exists flcs_seasons_select   on public.flcs_seasons;
drop policy if exists flcs_series_select    on public.flcs_series;
drop policy if exists flcs_events_select    on public.flcs_events;
drop policy if exists flcs_editions_select  on public.flcs_event_editions;
drop policy if exists flcs_stages_select    on public.flcs_stages;

create policy flcs_seasons_select  on public.flcs_seasons
  for select to anon, authenticated using (deleted_at is null);
create policy flcs_series_select   on public.flcs_series
  for select to anon, authenticated using (deleted_at is null);
create policy flcs_events_select   on public.flcs_events
  for select to anon, authenticated using (deleted_at is null);
create policy flcs_editions_select on public.flcs_event_editions
  for select to anon, authenticated using (deleted_at is null);
create policy flcs_stages_select   on public.flcs_stages
  for select to anon, authenticated using (deleted_at is null);

drop policy if exists flcs_seasons_write   on public.flcs_seasons;
drop policy if exists flcs_series_write    on public.flcs_series;
drop policy if exists flcs_events_write    on public.flcs_events;
drop policy if exists flcs_editions_write  on public.flcs_event_editions;
drop policy if exists flcs_stages_write    on public.flcs_stages;

create policy flcs_seasons_write  on public.flcs_seasons
  to authenticated using (public.pt_can_edit()) with check (public.pt_can_edit());
create policy flcs_series_write   on public.flcs_series
  to authenticated using (public.pt_can_edit()) with check (public.pt_can_edit());
create policy flcs_events_write   on public.flcs_events
  to authenticated using (public.pt_can_edit()) with check (public.pt_can_edit());
create policy flcs_editions_write on public.flcs_event_editions
  to authenticated using (public.pt_can_edit()) with check (public.pt_can_edit());
create policy flcs_stages_write   on public.flcs_stages
  to authenticated using (public.pt_can_edit()) with check (public.pt_can_edit());

grant select on public.flcs_calendar_v1 to anon, authenticated;

-- ------------------------------------------------------------
-- 9. Koppeling vanuit Publitool
--    Zachte referentie (geen FK): straks staat de editie in een
--    andere database. De snapshot-velden zijn puur voor weergave,
--    edition_id blijft de waarheid.
-- ------------------------------------------------------------
alter table public.publitool_parcours
  add column if not exists edition_id           uuid,
  add column if not exists edition_event_name   text,
  add column if not exists edition_event_date   date;

create index if not exists publitool_parcours_edition_idx
  on public.publitool_parcours (edition_id);

commit;

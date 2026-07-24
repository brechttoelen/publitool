-- ============================================================
--  Disciplines beheerbaar maken
--  Vervangt de CHECK-constraints op flcs_events / flcs_series
--  door een echte tabel met foreign key.
--
--  Draai dit NA flcs_calendar.sql.
-- ============================================================

begin;

-- ------------------------------------------------------------
-- 1. Tabel
--    slug is de sleutel waarnaar events en reeksen verwijzen.
--    UNIQUE staat bewust NIET op 'where deleted_at is null':
--    een foreign key heeft een volledige unieke constraint nodig,
--    en een slug hergebruiken na verwijderen wil je toch niet.
-- ------------------------------------------------------------
create table if not exists public.flcs_disciplines (
  id          uuid primary key default gen_random_uuid(),
  slug        text not null unique,
  name        text not null,
  sort_order  integer not null default 0,
  deleted_at  timestamptz,
  created_at  timestamptz not null default now(),
  updated_at  timestamptz not null default now()
);

drop trigger if exists flcs_disciplines_touch on public.flcs_disciplines;
create trigger flcs_disciplines_touch before update on public.flcs_disciplines
  for each row execute function public.touch_updated_at();

-- ------------------------------------------------------------
-- 2. De bestaande waarden overnemen
--    (dezelfde zes die in de CHECK-constraint stonden)
-- ------------------------------------------------------------
insert into public.flcs_disciplines (slug, name, sort_order) values
  ('veldrijden', 'Veldrijden', 10),
  ('weg',        'Weg',        20),
  ('mtb',        'MTB',        30),
  ('gravel',     'Gravel',     40),
  ('piste',      'Piste',      50),
  ('andere',     'Andere',     90)
on conflict (slug) do nothing;

-- Vangnet: staat er in de data een discipline die hier nog niet bij zit
-- (bv. handmatig ingevoegd), dan die alsnog aanmaken zodat de FK hieronder
-- niet struikelt.
insert into public.flcs_disciplines (slug, name, sort_order)
select distinct d.discipline, initcap(d.discipline), 100
from (
  select discipline from public.flcs_events  where discipline is not null
  union
  select discipline from public.flcs_series  where discipline is not null
) d
where not exists (
  select 1 from public.flcs_disciplines x where x.slug = d.discipline
)
on conflict (slug) do nothing;

-- ------------------------------------------------------------
-- 3. CHECK eruit, foreign key erin
-- ------------------------------------------------------------
alter table public.flcs_events
  drop constraint if exists flcs_events_discipline_chk;
alter table public.flcs_series
  drop constraint if exists flcs_series_discipline_chk;

alter table public.flcs_events
  drop constraint if exists flcs_events_discipline_fkey;
alter table public.flcs_events
  add constraint flcs_events_discipline_fkey
  foreign key (discipline) references public.flcs_disciplines(slug)
  on update cascade on delete restrict;

alter table public.flcs_series
  drop constraint if exists flcs_series_discipline_fkey;
alter table public.flcs_series
  add constraint flcs_series_discipline_fkey
  foreign key (discipline) references public.flcs_disciplines(slug)
  on update cascade on delete restrict;

-- ------------------------------------------------------------
-- 4. RLS — zelfde patroon als de rest van de kalender
-- ------------------------------------------------------------
alter table public.flcs_disciplines enable row level security;

drop policy if exists flcs_disciplines_select on public.flcs_disciplines;
create policy flcs_disciplines_select on public.flcs_disciplines
  for select to anon, authenticated using (deleted_at is null);

drop policy if exists flcs_disciplines_write on public.flcs_disciplines;
create policy flcs_disciplines_write on public.flcs_disciplines
  to authenticated using (public.pt_can_edit()) with check (public.pt_can_edit());

-- ------------------------------------------------------------
-- 5. View: discipline_name erbij (achteraan toegevoegd, dus
--    bestaande consumers van _v1 breken niet)
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
  ed.updated_at        as updated_at,
  di.name              as discipline_name
from public.flcs_event_editions ed
join public.flcs_events  ev on ev.id = ed.event_id
join public.flcs_seasons se on se.id = ed.season_id
left join public.flcs_series sr on sr.id = ed.series_id
left join public.flcs_disciplines di on di.slug = ev.discipline
where ed.deleted_at is null
  and ev.deleted_at is null
  and se.deleted_at is null;

alter view public.flcs_calendar_v1 set (security_invoker = on);
grant select on public.flcs_calendar_v1 to anon, authenticated;

commit;

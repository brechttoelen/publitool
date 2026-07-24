CREATE OR REPLACE FUNCTION public.publitool_share_view(p_token text)
 RETURNS jsonb
 LANGUAGE plpgsql
 STABLE SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
declare
  v_tok public.publitool_share_tokens%rowtype;
  v_par public.publitool_parcours%rowtype;
begin
  select * into v_tok from public.publitool_share_tokens
   where token = p_token and is_active limit 1;
  if not found then
    raise exception 'Link niet beschikbaar';
  end if;

  select * into v_par from public.publitool_parcours where id = v_tok.parcours_id;

  return jsonb_build_object(
    'parcours', jsonb_build_object(
      'id', v_par.id,
      'location_name', v_par.location_name,
      'race_date', v_par.race_date,
      'season_label', (select label from public.publitool_seasons s where s.id = v_par.season_id),
      'koepel_name',  (select name  from public.publitool_koepels k where k.id = v_par.koepel_id),
      'bg_image_path', v_par.bg_image_path,
      'bg_width',  v_par.bg_width,
      'bg_height', v_par.bg_height,
      'data', v_par.data
    ),
    'partners', coalesce((
      select jsonb_agg(jsonb_build_object(
        'id', p.id, 'name', p.name, 'color', p.color,
        'logoUrl', p.logo_path, 'is_local', p.is_local))
      from public.publitool_parcours_partners pp
      join public.publitool_partners p on p.id = pp.partner_id
      where pp.parcours_id = v_par.id
    ), '[]'::jsonb),
    'allowed_layers', v_tok.allowed_layers
  );
end $function$
;

CREATE OR REPLACE FUNCTION public.publitool_email_for_username(p_username text)
 RETURNS text
 LANGUAGE sql
 STABLE SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
  select u.email
  from public.hub_profiles p
  join auth.users u on u.id = p.id
  where lower(p.username) = lower(p_username)
  limit 1
$function$
;

CREATE OR REPLACE FUNCTION public.publitool_whoami()
 RETURNS jsonb
 LANGUAGE sql
 STABLE SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
  select jsonb_build_object(
    'id', p.id,
    'username', p.username,
    'display_name', p.display_name,
    'role', case r.slug when 'beheerder' then 'admin' else r.slug end,
    'has_full_access',
      (r.slug = 'beheerder')
      or coalesce((m.meta->>'has_full_access')::boolean, true)
  )
  from public.hub_profiles p
  join public.hub_app_members m on m.user_id = p.id
  join public.hub_apps a on a.id = m.app_id and a.slug = 'publitool'
  join public.hub_roles r on r.id = m.role_id
  where p.id = auth.uid()
$function$
;

CREATE OR REPLACE FUNCTION public.hub_is_member_of_app(p_app_id bigint)
 RETURNS boolean
 LANGUAGE sql
 STABLE SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
  select exists (
    select 1 from public.hub_app_members
    where app_id = p_app_id and user_id = auth.uid()
  )
$function$
;

CREATE OR REPLACE FUNCTION public.hub_is_app_admin(p_app_id bigint)
 RETURNS boolean
 LANGUAGE sql
 STABLE SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
  select exists (
    select 1
    from public.hub_app_members m
    join public.hub_roles r on r.id = m.role_id
    where m.app_id = p_app_id
      and m.user_id = auth.uid()
      and r.slug = 'beheerder'
  )
$function$


--
-- PostgreSQL database dump
--

\restrict f13LcxdUtT8lgM7CipLl6jcuDyoEDhet1txaouTx68pxrnglC8oo171yUiw60vM

-- Dumped from database version 17.6
-- Dumped by pg_dump version 18.4

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: hub_app_members; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.hub_app_members (
    user_id uuid NOT NULL,
    app_id bigint NOT NULL,
    role_id bigint NOT NULL,
    meta jsonb DEFAULT '{}'::jsonb NOT NULL,
    granted_by uuid,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: hub_apps; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.hub_apps (
    id bigint NOT NULL,
    slug text NOT NULL,
    name text NOT NULL,
    base_url text,
    icon_path text,
    sort_order integer DEFAULT 0 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: hub_apps_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.hub_apps ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.hub_apps_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: hub_profiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.hub_profiles (
    id uuid NOT NULL,
    username text NOT NULL,
    display_name text NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: hub_role_catalog; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.hub_role_catalog (
    slug text NOT NULL,
    label text NOT NULL,
    description text
);


--
-- Name: hub_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.hub_roles (
    id bigint NOT NULL,
    app_id bigint NOT NULL,
    slug text NOT NULL,
    label text NOT NULL,
    catalog_slug text
);


--
-- Name: hub_roles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.hub_roles ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.hub_roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: publitool_export_profiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.publitool_export_profiles (
    id bigint NOT NULL,
    name text NOT NULL,
    description text,
    format text DEFAULT 'pdf'::text NOT NULL,
    layers jsonb,
    include_led_page boolean DEFAULT false NOT NULL,
    created_by uuid,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: publitool_export_profiles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.publitool_export_profiles ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.publitool_export_profiles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: publitool_koepel_partners; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.publitool_koepel_partners (
    koepel_id bigint NOT NULL,
    partner_id bigint NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL
);


--
-- Name: publitool_koepels; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.publitool_koepels (
    id bigint NOT NULL,
    name text NOT NULL,
    short_name text,
    color text DEFAULT '#1c1917'::text NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: publitool_koepels_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.publitool_koepels ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.publitool_koepels_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: publitool_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.publitool_locks (
    parcours_id bigint NOT NULL,
    user_id uuid NOT NULL,
    user_name text NOT NULL,
    acquired_at timestamp with time zone DEFAULT now() NOT NULL,
    last_heartbeat_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: publitool_parcours; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.publitool_parcours (
    id bigint NOT NULL,
    season_id bigint NOT NULL,
    koepel_id bigint,
    location_name text NOT NULL,
    race_date date NOT NULL,
    bg_image_path text,
    bg_width integer,
    bg_height integer,
    data jsonb,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    bg_min_lat double precision,
    bg_max_lat double precision,
    bg_min_lng double precision,
    bg_max_lng double precision
);


--
-- Name: publitool_parcours_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.publitool_parcours ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.publitool_parcours_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: publitool_parcours_partners; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.publitool_parcours_partners (
    parcours_id bigint NOT NULL,
    partner_id bigint NOT NULL
);


--
-- Name: publitool_parcours_users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.publitool_parcours_users (
    parcours_id bigint NOT NULL,
    user_id uuid NOT NULL
);


--
-- Name: publitool_parcours_versions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.publitool_parcours_versions (
    id bigint NOT NULL,
    parcours_id bigint NOT NULL,
    snapshot jsonb NOT NULL,
    label text,
    kind text DEFAULT 'manual'::text NOT NULL,
    created_by_user_id uuid,
    created_by_name text,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: publitool_parcours_versions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.publitool_parcours_versions ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.publitool_parcours_versions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: publitool_partners; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.publitool_partners (
    id bigint NOT NULL,
    name text NOT NULL,
    color text DEFAULT '#888888'::text NOT NULL,
    logo_path text,
    is_local boolean DEFAULT false NOT NULL,
    notes text,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: publitool_partners_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.publitool_partners ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.publitool_partners_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: publitool_seasons; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.publitool_seasons (
    id bigint NOT NULL,
    label text NOT NULL,
    start_year smallint NOT NULL,
    end_year smallint NOT NULL,
    is_current boolean DEFAULT false NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: publitool_seasons_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.publitool_seasons ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.publitool_seasons_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: publitool_share_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.publitool_share_tokens (
    id bigint NOT NULL,
    parcours_id bigint NOT NULL,
    token text NOT NULL,
    label text,
    allowed_layers jsonb,
    is_active boolean DEFAULT true NOT NULL,
    created_by uuid,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: publitool_share_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.publitool_share_tokens ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.publitool_share_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: hub_app_members hub_app_members_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hub_app_members
    ADD CONSTRAINT hub_app_members_pkey PRIMARY KEY (user_id, app_id);


--
-- Name: hub_apps hub_apps_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hub_apps
    ADD CONSTRAINT hub_apps_pkey PRIMARY KEY (id);


--
-- Name: hub_apps hub_apps_slug_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hub_apps
    ADD CONSTRAINT hub_apps_slug_key UNIQUE (slug);


--
-- Name: hub_profiles hub_profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hub_profiles
    ADD CONSTRAINT hub_profiles_pkey PRIMARY KEY (id);


--
-- Name: hub_profiles hub_profiles_username_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hub_profiles
    ADD CONSTRAINT hub_profiles_username_key UNIQUE (username);


--
-- Name: hub_role_catalog hub_role_catalog_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hub_role_catalog
    ADD CONSTRAINT hub_role_catalog_pkey PRIMARY KEY (slug);


--
-- Name: hub_roles hub_roles_app_id_slug_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hub_roles
    ADD CONSTRAINT hub_roles_app_id_slug_key UNIQUE (app_id, slug);


--
-- Name: hub_roles hub_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hub_roles
    ADD CONSTRAINT hub_roles_pkey PRIMARY KEY (id);


--
-- Name: publitool_export_profiles publitool_export_profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publitool_export_profiles
    ADD CONSTRAINT publitool_export_profiles_pkey PRIMARY KEY (id);


--
-- Name: publitool_koepel_partners publitool_koepel_partners_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publitool_koepel_partners
    ADD CONSTRAINT publitool_koepel_partners_pkey PRIMARY KEY (koepel_id, partner_id);


--
-- Name: publitool_koepels publitool_koepels_name_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publitool_koepels
    ADD CONSTRAINT publitool_koepels_name_key UNIQUE (name);


--
-- Name: publitool_koepels publitool_koepels_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publitool_koepels
    ADD CONSTRAINT publitool_koepels_pkey PRIMARY KEY (id);


--
-- Name: publitool_locks publitool_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publitool_locks
    ADD CONSTRAINT publitool_locks_pkey PRIMARY KEY (parcours_id);


--
-- Name: publitool_parcours_partners publitool_parcours_partners_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publitool_parcours_partners
    ADD CONSTRAINT publitool_parcours_partners_pkey PRIMARY KEY (parcours_id, partner_id);


--
-- Name: publitool_parcours publitool_parcours_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publitool_parcours
    ADD CONSTRAINT publitool_parcours_pkey PRIMARY KEY (id);


--
-- Name: publitool_parcours_users publitool_parcours_users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publitool_parcours_users
    ADD CONSTRAINT publitool_parcours_users_pkey PRIMARY KEY (parcours_id, user_id);


--
-- Name: publitool_parcours_versions publitool_parcours_versions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publitool_parcours_versions
    ADD CONSTRAINT publitool_parcours_versions_pkey PRIMARY KEY (id);


--
-- Name: publitool_partners publitool_partners_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publitool_partners
    ADD CONSTRAINT publitool_partners_pkey PRIMARY KEY (id);


--
-- Name: publitool_seasons publitool_seasons_label_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publitool_seasons
    ADD CONSTRAINT publitool_seasons_label_key UNIQUE (label);


--
-- Name: publitool_seasons publitool_seasons_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publitool_seasons
    ADD CONSTRAINT publitool_seasons_pkey PRIMARY KEY (id);


--
-- Name: publitool_share_tokens publitool_share_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publitool_share_tokens
    ADD CONSTRAINT publitool_share_tokens_pkey PRIMARY KEY (id);


--
-- Name: publitool_share_tokens publitool_share_tokens_token_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publitool_share_tokens
    ADD CONSTRAINT publitool_share_tokens_token_key UNIQUE (token);


--
-- Name: publitool_parcours_date_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX publitool_parcours_date_idx ON public.publitool_parcours USING btree (race_date);


--
-- Name: publitool_parcours_koepel_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX publitool_parcours_koepel_idx ON public.publitool_parcours USING btree (koepel_id);


--
-- Name: publitool_parcours_season_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX publitool_parcours_season_idx ON public.publitool_parcours USING btree (season_id);


--
-- Name: publitool_share_tokens_token_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX publitool_share_tokens_token_idx ON public.publitool_share_tokens USING btree (token) WHERE is_active;


--
-- Name: publitool_versions_parcours_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX publitool_versions_parcours_idx ON public.publitool_parcours_versions USING btree (parcours_id, created_at DESC);


--
-- Name: publitool_parcours publitool_parcours_touch; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER publitool_parcours_touch BEFORE UPDATE ON public.publitool_parcours FOR EACH ROW EXECUTE FUNCTION public.touch_updated_at();


--
-- Name: publitool_partners publitool_partners_touch; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER publitool_partners_touch BEFORE UPDATE ON public.publitool_partners FOR EACH ROW EXECUTE FUNCTION public.touch_updated_at();


--
-- Name: hub_app_members hub_app_members_app_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hub_app_members
    ADD CONSTRAINT hub_app_members_app_id_fkey FOREIGN KEY (app_id) REFERENCES public.hub_apps(id) ON DELETE CASCADE;


--
-- Name: hub_app_members hub_app_members_granted_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hub_app_members
    ADD CONSTRAINT hub_app_members_granted_by_fkey FOREIGN KEY (granted_by) REFERENCES public.hub_profiles(id) ON DELETE SET NULL;


--
-- Name: hub_app_members hub_app_members_role_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hub_app_members
    ADD CONSTRAINT hub_app_members_role_id_fkey FOREIGN KEY (role_id) REFERENCES public.hub_roles(id) ON DELETE CASCADE;


--
-- Name: hub_app_members hub_app_members_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hub_app_members
    ADD CONSTRAINT hub_app_members_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.hub_profiles(id) ON DELETE CASCADE;


--
-- Name: hub_profiles hub_profiles_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hub_profiles
    ADD CONSTRAINT hub_profiles_id_fkey FOREIGN KEY (id) REFERENCES auth.users(id) ON DELETE CASCADE;


--
-- Name: hub_roles hub_roles_app_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hub_roles
    ADD CONSTRAINT hub_roles_app_id_fkey FOREIGN KEY (app_id) REFERENCES public.hub_apps(id) ON DELETE CASCADE;


--
-- Name: hub_roles hub_roles_catalog_slug_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hub_roles
    ADD CONSTRAINT hub_roles_catalog_slug_fkey FOREIGN KEY (catalog_slug) REFERENCES public.hub_role_catalog(slug);


--
-- Name: publitool_export_profiles publitool_export_profiles_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publitool_export_profiles
    ADD CONSTRAINT publitool_export_profiles_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.hub_profiles(id) ON DELETE SET NULL;


--
-- Name: publitool_koepel_partners publitool_koepel_partners_koepel_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publitool_koepel_partners
    ADD CONSTRAINT publitool_koepel_partners_koepel_id_fkey FOREIGN KEY (koepel_id) REFERENCES public.publitool_koepels(id) ON DELETE CASCADE;


--
-- Name: publitool_koepel_partners publitool_koepel_partners_partner_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publitool_koepel_partners
    ADD CONSTRAINT publitool_koepel_partners_partner_id_fkey FOREIGN KEY (partner_id) REFERENCES public.publitool_partners(id) ON DELETE CASCADE;


--
-- Name: publitool_locks publitool_locks_parcours_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publitool_locks
    ADD CONSTRAINT publitool_locks_parcours_id_fkey FOREIGN KEY (parcours_id) REFERENCES public.publitool_parcours(id) ON DELETE CASCADE;


--
-- Name: publitool_locks publitool_locks_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publitool_locks
    ADD CONSTRAINT publitool_locks_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.hub_profiles(id) ON DELETE CASCADE;


--
-- Name: publitool_parcours publitool_parcours_koepel_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publitool_parcours
    ADD CONSTRAINT publitool_parcours_koepel_id_fkey FOREIGN KEY (koepel_id) REFERENCES public.publitool_koepels(id) ON DELETE SET NULL;


--
-- Name: publitool_parcours_partners publitool_parcours_partners_parcours_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publitool_parcours_partners
    ADD CONSTRAINT publitool_parcours_partners_parcours_id_fkey FOREIGN KEY (parcours_id) REFERENCES public.publitool_parcours(id) ON DELETE CASCADE;


--
-- Name: publitool_parcours_partners publitool_parcours_partners_partner_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publitool_parcours_partners
    ADD CONSTRAINT publitool_parcours_partners_partner_id_fkey FOREIGN KEY (partner_id) REFERENCES public.publitool_partners(id) ON DELETE CASCADE;


--
-- Name: publitool_parcours publitool_parcours_season_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publitool_parcours
    ADD CONSTRAINT publitool_parcours_season_id_fkey FOREIGN KEY (season_id) REFERENCES public.publitool_seasons(id) ON DELETE RESTRICT;


--
-- Name: publitool_parcours_users publitool_parcours_users_parcours_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publitool_parcours_users
    ADD CONSTRAINT publitool_parcours_users_parcours_id_fkey FOREIGN KEY (parcours_id) REFERENCES public.publitool_parcours(id) ON DELETE CASCADE;


--
-- Name: publitool_parcours_users publitool_parcours_users_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publitool_parcours_users
    ADD CONSTRAINT publitool_parcours_users_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.hub_profiles(id) ON DELETE CASCADE;


--
-- Name: publitool_parcours_versions publitool_parcours_versions_created_by_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publitool_parcours_versions
    ADD CONSTRAINT publitool_parcours_versions_created_by_user_id_fkey FOREIGN KEY (created_by_user_id) REFERENCES public.hub_profiles(id) ON DELETE SET NULL;


--
-- Name: publitool_parcours_versions publitool_parcours_versions_parcours_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publitool_parcours_versions
    ADD CONSTRAINT publitool_parcours_versions_parcours_id_fkey FOREIGN KEY (parcours_id) REFERENCES public.publitool_parcours(id) ON DELETE CASCADE;


--
-- Name: publitool_share_tokens publitool_share_tokens_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publitool_share_tokens
    ADD CONSTRAINT publitool_share_tokens_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.hub_profiles(id) ON DELETE SET NULL;


--
-- Name: publitool_share_tokens publitool_share_tokens_parcours_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.publitool_share_tokens
    ADD CONSTRAINT publitool_share_tokens_parcours_id_fkey FOREIGN KEY (parcours_id) REFERENCES public.publitool_parcours(id) ON DELETE CASCADE;


--
-- Name: hub_apps apps_admin_write; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY apps_admin_write ON public.hub_apps TO authenticated USING (public.is_hub_admin()) WITH CHECK (public.is_hub_admin());


--
-- Name: hub_apps apps_select_member; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY apps_select_member ON public.hub_apps FOR SELECT TO authenticated USING ((public.is_hub_admin() OR public.hub_is_member_of_app(id)));


--
-- Name: hub_role_catalog catalog_admin_write; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY catalog_admin_write ON public.hub_role_catalog TO authenticated USING (public.is_hub_admin()) WITH CHECK (public.is_hub_admin());


--
-- Name: hub_role_catalog catalog_select; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY catalog_select ON public.hub_role_catalog FOR SELECT TO authenticated USING (true);


--
-- Name: publitool_export_profiles export_profiles_select; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY export_profiles_select ON public.publitool_export_profiles FOR SELECT TO authenticated USING (public.pt_is_member());


--
-- Name: publitool_export_profiles export_profiles_write; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY export_profiles_write ON public.publitool_export_profiles TO authenticated USING (public.pt_can_edit()) WITH CHECK (public.pt_can_edit());


--
-- Name: hub_app_members; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.hub_app_members ENABLE ROW LEVEL SECURITY;

--
-- Name: hub_apps; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.hub_apps ENABLE ROW LEVEL SECURITY;

--
-- Name: hub_profiles; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.hub_profiles ENABLE ROW LEVEL SECURITY;

--
-- Name: hub_role_catalog; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.hub_role_catalog ENABLE ROW LEVEL SECURITY;

--
-- Name: hub_roles; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.hub_roles ENABLE ROW LEVEL SECURITY;

--
-- Name: publitool_koepel_partners koepel_partners_select; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY koepel_partners_select ON public.publitool_koepel_partners FOR SELECT TO authenticated USING (public.pt_is_member());


--
-- Name: publitool_koepel_partners koepel_partners_write; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY koepel_partners_write ON public.publitool_koepel_partners TO authenticated USING (public.pt_can_edit()) WITH CHECK (public.pt_can_edit());


--
-- Name: publitool_koepels koepels_select; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY koepels_select ON public.publitool_koepels FOR SELECT TO authenticated USING (public.pt_is_member());


--
-- Name: publitool_koepels koepels_write; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY koepels_write ON public.publitool_koepels TO authenticated USING (public.pt_can_edit()) WITH CHECK (public.pt_can_edit());


--
-- Name: publitool_locks locks_member_all; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY locks_member_all ON public.publitool_locks TO authenticated USING (public.pt_is_member()) WITH CHECK (public.pt_is_member());


--
-- Name: hub_app_members members_admin_write; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY members_admin_write ON public.hub_app_members TO authenticated USING (public.is_hub_admin()) WITH CHECK (public.is_hub_admin());


--
-- Name: hub_app_members members_select_app_admin; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY members_select_app_admin ON public.hub_app_members FOR SELECT TO authenticated USING ((public.is_hub_admin() OR public.hub_is_app_admin(app_id)));


--
-- Name: hub_app_members members_select_own; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY members_select_own ON public.hub_app_members FOR SELECT TO authenticated USING ((user_id = auth.uid()));


--
-- Name: publitool_parcours_partners parcours_partners_select; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY parcours_partners_select ON public.publitool_parcours_partners FOR SELECT TO authenticated USING ((public.pt_is_member() AND public.pt_can_see_parcours(parcours_id)));


--
-- Name: publitool_parcours_partners parcours_partners_write; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY parcours_partners_write ON public.publitool_parcours_partners TO authenticated USING ((public.pt_can_edit() AND public.pt_can_see_parcours(parcours_id))) WITH CHECK (public.pt_can_edit());


--
-- Name: publitool_parcours parcours_select; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY parcours_select ON public.publitool_parcours FOR SELECT TO authenticated USING ((public.pt_is_member() AND public.pt_can_see_parcours(id)));


--
-- Name: publitool_parcours_users parcours_users_select; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY parcours_users_select ON public.publitool_parcours_users FOR SELECT TO authenticated USING (public.pt_is_member());


--
-- Name: publitool_parcours_users parcours_users_write; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY parcours_users_write ON public.publitool_parcours_users TO authenticated USING (public.pt_is_admin()) WITH CHECK (public.pt_is_admin());


--
-- Name: publitool_parcours parcours_write; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY parcours_write ON public.publitool_parcours TO authenticated USING ((public.pt_can_edit() AND public.pt_can_see_parcours(id))) WITH CHECK (public.pt_can_edit());


--
-- Name: publitool_partners partners_select; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY partners_select ON public.publitool_partners FOR SELECT TO authenticated USING (public.pt_is_member());


--
-- Name: publitool_partners partners_write; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY partners_write ON public.publitool_partners TO authenticated USING (public.pt_can_edit()) WITH CHECK (public.pt_can_edit());


--
-- Name: hub_profiles profiles_admin_write; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY profiles_admin_write ON public.hub_profiles TO authenticated USING (public.is_hub_admin()) WITH CHECK (public.is_hub_admin());


--
-- Name: hub_profiles profiles_select; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY profiles_select ON public.hub_profiles FOR SELECT TO authenticated USING (true);


--
-- Name: publitool_export_profiles; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.publitool_export_profiles ENABLE ROW LEVEL SECURITY;

--
-- Name: publitool_koepel_partners; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.publitool_koepel_partners ENABLE ROW LEVEL SECURITY;

--
-- Name: publitool_koepels; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.publitool_koepels ENABLE ROW LEVEL SECURITY;

--
-- Name: publitool_locks; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.publitool_locks ENABLE ROW LEVEL SECURITY;

--
-- Name: publitool_parcours; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.publitool_parcours ENABLE ROW LEVEL SECURITY;

--
-- Name: publitool_parcours_partners; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.publitool_parcours_partners ENABLE ROW LEVEL SECURITY;

--
-- Name: publitool_parcours_users; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.publitool_parcours_users ENABLE ROW LEVEL SECURITY;

--
-- Name: publitool_parcours_versions; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.publitool_parcours_versions ENABLE ROW LEVEL SECURITY;

--
-- Name: publitool_partners; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.publitool_partners ENABLE ROW LEVEL SECURITY;

--
-- Name: publitool_seasons; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.publitool_seasons ENABLE ROW LEVEL SECURITY;

--
-- Name: publitool_share_tokens; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.publitool_share_tokens ENABLE ROW LEVEL SECURITY;

--
-- Name: hub_roles roles_admin_write; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY roles_admin_write ON public.hub_roles TO authenticated USING (public.is_hub_admin()) WITH CHECK (public.is_hub_admin());


--
-- Name: hub_roles roles_select; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY roles_select ON public.hub_roles FOR SELECT TO authenticated USING (true);


--
-- Name: publitool_seasons seasons_select; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY seasons_select ON public.publitool_seasons FOR SELECT TO authenticated USING (public.pt_is_member());


--
-- Name: publitool_seasons seasons_write; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY seasons_write ON public.publitool_seasons TO authenticated USING (public.pt_can_edit()) WITH CHECK (public.pt_can_edit());


--
-- Name: publitool_share_tokens share_tokens_select; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY share_tokens_select ON public.publitool_share_tokens FOR SELECT TO authenticated USING ((public.pt_is_member() AND public.pt_can_see_parcours(parcours_id)));


--
-- Name: publitool_share_tokens share_tokens_write; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY share_tokens_write ON public.publitool_share_tokens TO authenticated USING ((public.pt_can_edit() AND public.pt_can_see_parcours(parcours_id))) WITH CHECK (public.pt_can_edit());


--
-- Name: publitool_parcours_versions versions_select; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY versions_select ON public.publitool_parcours_versions FOR SELECT TO authenticated USING ((public.pt_is_member() AND public.pt_can_see_parcours(parcours_id)));


--
-- Name: publitool_parcours_versions versions_write; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY versions_write ON public.publitool_parcours_versions TO authenticated USING ((public.pt_can_edit() AND public.pt_can_see_parcours(parcours_id))) WITH CHECK (public.pt_can_edit());


--
-- PostgreSQL database dump complete
--

\unrestrict f13LcxdUtT8lgM7CipLl6jcuDyoEDhet1txaouTx68pxrnglC8oo171yUiw60vM


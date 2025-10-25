-- Migration: create admin_activity_logs and add is_active to users
-- Run this once in Supabase/Postgres as a superuser or via your migration tool.

BEGIN;

-- Create activity logs table (no IP column)
CREATE TABLE IF NOT EXISTS public.admin_activity_logs (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  admin_user_id uuid,
  admin_department text,
  action text,
  details text,
  created_at timestamptz NOT NULL DEFAULT now()
);

-- Add is_active column to users if missing
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'users' AND column_name = 'is_active'
    ) THEN
        ALTER TABLE public.users ADD COLUMN is_active boolean NOT NULL DEFAULT false;
    END IF;
END$$;

COMMIT;
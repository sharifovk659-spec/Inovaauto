-- Supabase REST proxy for Hostinger (no pdo_pgsql).
-- Run once in Supabase → SQL Editor after deploy.
-- PHP calls: POST /rest/v1/rpc/ia_db_execute

CREATE OR REPLACE FUNCTION public.ia_db_execute(
    p_sql text,
    p_args jsonb DEFAULT '[]'::jsonb
)
RETURNS jsonb
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, pg_temp
AS $$
DECLARE
    v_cmd text;
    v_rows jsonb := '[]'::jsonb;
    v_row record;
    v_affected int := 0;
    v_final_sql text;
    v_args text[];
    i int;
    v_arg text;
BEGIN
    IF p_sql IS NULL OR btrim(p_sql) = '' THEN
        RETURN jsonb_build_object('ok', false, 'error', 'empty sql');
    END IF;

    IF jsonb_typeof(COALESCE(p_args, '[]'::jsonb)) <> 'array' THEN
        p_args := '[]'::jsonb;
    END IF;

    SELECT COALESCE(array_agg(
        CASE
            WHEN elem = 'null'::jsonb THEN NULL
            WHEN jsonb_typeof(elem) = 'string' THEN elem #>> '{}'
            WHEN jsonb_typeof(elem) = 'boolean' THEN CASE WHEN elem::boolean THEN 'true' ELSE 'false' END
            ELSE trim(both '"' from elem::text)
        END
    ), ARRAY[]::text[])
    INTO v_args
    FROM jsonb_array_elements(p_args) AS elem;

    v_final_sql := p_sql;
    IF v_args IS NOT NULL AND array_length(v_args, 1) IS NOT NULL THEN
        FOR i IN REVERSE array_length(v_args, 1)..1 LOOP
            v_arg := v_args[i];
            IF v_arg IS NULL THEN
                v_final_sql := replace(v_final_sql, '$' || i, 'NULL');
            ELSE
                v_final_sql := replace(v_final_sql, '$' || i, quote_literal(v_arg));
            END IF;
        END LOOP;
    END IF;

    v_cmd := upper(split_part(btrim(v_final_sql), ' ', 1));

    IF v_cmd IN ('SELECT', 'WITH') OR v_final_sql ILIKE '%RETURNING%' THEN
        FOR v_row IN EXECUTE v_final_sql LOOP
            v_rows := v_rows || jsonb_build_array(to_jsonb(v_row));
        END LOOP;
        RETURN jsonb_build_object('ok', true, 'rows', v_rows, 'affected', 0);
    END IF;

    EXECUTE v_final_sql;
    GET DIAGNOSTICS v_affected = ROW_COUNT;
    RETURN jsonb_build_object('ok', true, 'rows', '[]'::jsonb, 'affected', v_affected);
EXCEPTION
    WHEN OTHERS THEN
        RETURN jsonb_build_object('ok', false, 'error', SQLERRM);
END;
$$;

REVOKE ALL ON FUNCTION public.ia_db_execute(text, jsonb) FROM PUBLIC;
GRANT EXECUTE ON FUNCTION public.ia_db_execute(text, jsonb) TO service_role;
GRANT EXECUTE ON FUNCTION public.ia_db_execute(text, jsonb) TO postgres;

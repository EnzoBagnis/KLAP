-- vues pour les stats, calculées à partir des fiches soumises (rien n'est stocké)

-- participants d'un sous-projet = max des présents sur ses séances (pas la somme, cf. retour de Mariette)
CREATE OR REPLACE VIEW v_sub_project_participants AS
SELECT ssp.sub_project_id,
       ssp.project_id,
       MAX(ssp.attendees) AS participants,
       COUNT(DISTINCT ssp.session_id) AS sessions_documented
FROM session_sub_projects ssp
JOIN logbook_entries le ON le.session_id = ssp.session_id
WHERE le.status IN ('submitted','locked')
GROUP BY ssp.sub_project_id, ssp.project_id;

-- participants d'un projet = somme des max de chaque sous-projet
CREATE OR REPLACE VIEW v_project_participants AS
SELECT p.id AS project_id,
       p.name,
       COALESCE(SUM(v.participants), 0) AS participants
FROM projects p
LEFT JOIN v_sub_project_participants v ON v.project_id = p.id
GROUP BY p.id, p.name;

-- moyennes engagement / expression / autonomie par projet
CREATE OR REPLACE VIEW v_project_indicators AS
SELECT s.project_id,
       COUNT(*)                     AS entries,
       ROUND(AVG(pe.engagement), 2) AS avg_engagement,
       ROUND(AVG(pe.expression), 2) AS avg_expression,
       ROUND(AVG(pe.autonomy), 2)   AS avg_autonomy
FROM logbook_entries le
JOIN scheduled_sessions s ON s.id = le.session_id
JOIN participant_evaluations pe ON pe.logbook_entry_id = le.id
WHERE le.status IN ('submitted','locked')
GROUP BY s.project_id;

-- séances passées dont la fiche n'a pas été rendue
CREATE OR REPLACE VIEW v_missing_entries AS
SELECT s.id AS session_id, s.project_id, s.session_date, s.referent_id
FROM scheduled_sessions s
LEFT JOIN logbook_entries le ON le.session_id = s.id AND le.status IN ('submitted','locked')
WHERE s.status <> 'cancelled'
  AND s.session_date < CURRENT_DATE
  AND le.id IS NULL;

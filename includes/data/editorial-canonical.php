<?php
/** Canonical projection; the SHA identifies the supplied canonical pin, not this PHP file. */
return [
    'knowledge_id' => 'editorial.canonical',
    'schema_version' => 1,
    'canonical_version' => '1.1.0',
    'canonical_sha256' => '0bc762b7666ffead0c54dbe83ecddc281ed4a9f2e7bdbaaf61406b3e921c9acf',
    'global' => [
        'identity' => ['thesis' => 'leer el presente a través del diseño', 'voice' => 'Diseñador Traductor', 'depth' => 'profundidad sin densidad'],
        'evidence' => [
            'levels' => ['fact', 'editorial_interpretation', 'limited_inference'],
            'principles' => ['source_verifies_not_narrates' => true, 'evidence_witness' => true, 'observable_evidence_preferred' => true, 'unsupported_claims_forbidden' => true],
            'precedence' => ['evidence_over_taxonomy' => true, 'evidence_over_recipe' => true, 'evidence_over_lens' => true],
        ],
        'writing_principles' => [
            'show_before_explaining_importance' => true,
            'non_promotional' => true,
            'open_disciplinary_language' => true,
            'avoid_press_release_voice' => true,
            'avoid_catalogue_voice' => true,
            'thesis_should_be_shown_not_announced' => true,
            'concrete_design_decisions_first' => true,
            'explain_design_decisions_not_only_features' => true,
            'follow_decision_to_relevant_consequence_then_stop' => true,
            'abstraction_and_qualities_after_concrete_evidence' => true,
            'author_intent_requires_documentation' => true,
            'avoid_meta_editorial_language_when_it_replaces_observation' => true,
            'authorship_is_transversal_and_tied_to_concrete_decisions' => true,
            'category_appropriate_experience_and_perception_vocabulary' => true,
            'avoid_overexplaining_interpret_only_for_new_relation' => true,
        ],
    ],
    'surfaces' => [
        'article' => [
            'title' => ['scope' => 'editorial_h1_not_seo_title', 'preferred_max_chars' => 60, 'hard_max_chars' => 68],
            'introduction' => ['standard_paragraphs' => 2, 'enforcement' => 'preferred', 'purpose' => ['identify_subject', 'establish_relevance', 'establish_editorial_angle', 'orientation_from_design', 'central_design_decisions', 'editorial_direction', 'avoid_editorial_box_repetition'], 'non_rigid' => true],
            'editorial_box' => ['required' => true, 'min_words' => 40, 'max_words' => 55, 'must_answer' => ['what_it_is', 'who_is_responsible', 'for_whom_when_relevant', 'origin_or_current_state_when_relevant', 'essential_objective_traits'], 'forbidden' => ['links', 'bold', 'promotional_language'], 'position' => 'after_introduction', 'guardrails' => ['primarily_factual' => true, 'do_not_repeat_editorial_thesis' => true, 'do_not_advance_body_analysis' => true]],
            'headings' => ['hierarchy_required' => true, 'style' => 'descriptive_and_concise', 'analysis_axis_clear_without_forcing_full_thesis' => true, 'analysis_axis_guidance' => 'non_rigid', 'avoid' => 'single_underdeveloped_paragraph', 'merge_or_expand_when_needed' => true],
            'transitions' => ['guidance' => 'flexible', 'use_continuity_when_natural' => true, 'allow_direct_cuts_when_axis_clearly_changes' => true, 'mandatory_bridge' => false],
            'closing' => ['recover_main_design_logic' => true, 'may_connect_to_present_when_supported_by_design' => true, 'prefer_open_or_observational_over_definitive_verdict' => true],
            'links' => [
                'responsible_external' => ['required' => true, 'count' => 1, 'target' => 'responsible_official_url', 'contextual' => true, 'standalone' => false, 'responsible_entity' => ['designer', 'studio', 'brand', 'organization', 'project_owner']],
                'taxonomy_internal' => ['required' => true, 'count' => 1, 'target' => 'resolved_editorial_tag', 'contextual' => true, 'standalone' => false, 'resolution' => ['first' => 'primary_lens_tag', 'second' => 'approved_secondary_lens_tag'], 'invented_tag' => false, 'arbitrary_post' => false, 'category_landing_fallback' => false, 'unresolved_behavior' => 'require_editorial_resolution'],
            ],
        ],
        'calendar_event' => [
            'distinct_from' => 'article', 'implementation_surface' => 'wordpress_custom_plugin', 'editorial_behavior' => 'event_record',
            'required_facts' => ['event_name', 'start_date', 'end_date', 'city', 'country', 'venue_when_available', 'official_source'],
            'taxonomies' => ['country' => ['type' => 'country'], 'event_category' => ['values' => ['Arquitectura e interiores', 'Diseño digital y 3D', 'Diseño interdisciplinar', 'Moda', 'Movilidad y transporte', 'Semana de diseño']]],
            'editorial_purpose' => ['what', 'when', 'where', 'why_it_matters'],
        ],
    ],
    'categories' => [
        'product' => ['axes' => ['uso', 'forma/proporción', 'ergonomía', 'materiales/acabados', 'interacción', 'fabricación', 'durabilidad', 'entorno cotidiano'], 'identity' => ['required' => false, 'use_when_supported_by_evidence' => true], 'preferred_progression' => ['object/construction', 'form/material', 'body/interaction', 'use/experience', 'space', 'authorship/production/trajectory', 'recognition'], 'non_rigid' => true],
        'architecture_interiors' => ['axes' => ['organización espacial', 'circulación', 'luz', 'escala', 'materialidad', 'estructura', 'interior-exterior', 'clima', 'programa'], 'identity' => ['required' => false, 'use_when_supported_by_evidence' => true], 'preferred_progression' => ['plan/spatial_operation', 'access', 'route', 'light/materiality/scale', 'everyday_life', 'intimacy', 'authorship/studio'], 'non_rigid' => true],
        'fashion' => ['axes' => ['silueta', 'construcción', 'textiles', 'color', 'movimiento', 'cuerpo', 'técnica artesanal/industrial', 'contexto cultural'], 'identity' => ['required' => false, 'use_when_supported_by_evidence' => true], 'preferred_progression' => ['silhouette/proportion', 'materials/construction', 'body', 'movement', 'perception', 'cultural_or_urban_context', 'authorship'], 'non_rigid' => true],
        'mobility' => ['axes' => ['exterior', 'arquitectura vehículo', 'interior', 'ergonomía', 'interfaz', 'materiales', 'seguridad cuando haya evidencia', 'experiencia de desplazamiento', 'infraestructura/contexto'], 'identity' => ['required' => false, 'use_when_supported_by_evidence' => true], 'preferred_progression' => ['exterior/proportion', 'access', 'cabin', 'interface/controls', 'driver_vehicle_relation', 'displacement_experience', 'authorship', 'brand_context'], 'non_rigid' => true],
        'digital_3d' => ['axes' => ['interfaz', 'jerarquía', 'navegación', 'movimiento', 'representación', 'accesibilidad', 'interacción', 'tecnología', 'workflow', 'lenguaje visual'], 'identity' => ['required' => false, 'use_when_supported_by_evidence' => true], 'preferred_progression' => ['novelty_or_hook', 'central_function', 'specific_functions', 'workflow', 'professional_situation_or_utility', 'authorship', 'availability_or_context'], 'non_rigid' => true],
        'contests_calls' => ['editorial_mode' => 'practical_opportunity', 'priority' => ['purpose', 'organizer', 'eligibility', 'disciplines', 'closing_date', 'confirmed_prizes', 'participation_value'], 'avoid' => ['promotional_voice', 'requirements_dump', 'unsupported_information'], 'preferred_progression' => ['what_it_is_and_who_calls', 'eligibility_and_disciplines', 'what_it_seeks_or_criteria', 'prizes_and_value', 'dates_and_deadline', 'how_to_participate', 'real_fit'], 'non_rigid' => true],
    ],
    'lenses' => [
        'model' => 'semantic', 'seo_authority' => false, 'primary_lens' => 'explicit_when_present', 'secondaries' => 'additive', 'wordpress_tag_order_authoritative' => false, 'unknown_tags' => 'allowed', 'evidence_precedence' => true,
        'definitions' => [
            'furniture' => ['focus' => 'Relación entre cuerpo, uso y objeto en el espacio cotidiano.', 'axes' => ['ergonomía', 'proporción', 'construcción', 'materiales', 'uso']],
            'lighting' => ['focus' => 'La luz como experiencia espacial y resultado de decisiones de diseño.', 'axes' => ['distribución de luz', 'atmósfera', 'relación con el espacio', 'materialidad']],
            'materiality' => ['focus' => 'Cómo las propiedades y transformaciones del material participan en el diseño.', 'axes' => ['propiedades observables', 'procesos', 'acabados', 'comportamiento en uso']],
            'automotive' => ['focus' => 'El vehículo como sistema de forma, habitabilidad e interacción.', 'axes' => ['arquitectura vehículo', 'exterior', 'interior', 'interfaz', 'desplazamiento']],
            'generative_ai' => ['focus' => 'Papel verificable de la IA generativa en el proceso y resultado de diseño.', 'axes' => ['workflow', 'intervención humana', 'representación', 'límites observables']],
            'sustainability' => ['focus' => 'Decisiones ambientales sustentadas en evidencia concreta y límites explícitos.', 'axes' => ['materiales', 'fabricación', 'durabilidad', 'reparación', 'ciclo de vida documentado'], 'unsupported_environmental_claims_forbidden' => true],
        ],
    ],
    'derived_formats' => ['reel' => ['scenes' => 6, 'voiceover' => ['scenes_1_to_5' => ['words' => 14], 'scene_6' => ['fixed_cta_required' => true]], 'overlays' => ['per_scene' => 3, 'max_chars' => 40], 'fixed_cta' => 'Conoce más de este proyecto en ideasDi.com']],
    'resolution' => [
        'sequence' => ['global', 'surface', 'category', 'primary_lens', 'secondary_lenses', 'piece_context'],
        'piece_context' => ['canonical_policy' => false, 'source' => 'runtime_input', 'allowed_fields' => ['sources', 'verified_facts', 'brief', 'responsible_entity', 'evidence', 'angle', 'article_specific_constraints']],
        'hard_global_guardrails_overridable' => false, 'evidence_over_suggestions' => ['surface', 'category', 'lens'], 'taxonomy_can_create_facts' => false, 'taxonomy_can_force_angle' => false,
    ],
];

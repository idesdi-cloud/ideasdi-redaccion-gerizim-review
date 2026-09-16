#!/usr/bin/env python3
"""RC1.7.1: literal fidelity checks for the editorial.canonical 1.1.0 projection."""

import json
import subprocess
from pathlib import Path


ROOT = Path(__file__).resolve().parent.parent
CANONICAL = ROOT / 'includes' / 'data' / 'editorial-canonical.php'


def load_projection():
    result = subprocess.run(
        ['php', '-r', '$projection = require $argv[1]; echo json_encode($projection, JSON_THROW_ON_ERROR);', str(CANONICAL)],
        check=True,
        capture_output=True,
        text=True,
    )
    return json.loads(result.stdout)


def same(actual, expected, label):
    if actual != expected:
        raise AssertionError(f'{label}: expected {expected!r}, got {actual!r}')


projection = load_projection()

same(projection['knowledge_id'], 'editorial.canonical', 'knowledge_id')
same(projection['schema_version'], 1, 'schema_version')
same(projection['canonical_version'], '1.1.0', 'canonical_version')
same(projection['canonical_sha256'], '0bc762b7666ffead0c54dbe83ecddc281ed4a9f2e7bdbaaf61406b3e921c9acf', 'canonical_sha256')

writing_principles = projection['global']['writing_principles']
same(list(writing_principles), [
    'show_before_explaining_importance',
    'non_promotional',
    'open_disciplinary_language',
    'avoid_press_release_voice',
    'avoid_catalogue_voice',
    'thesis_should_be_shown_not_announced',
    'concrete_design_decisions_first',
    'explain_design_decisions_not_only_features',
    'follow_decision_to_relevant_consequence_then_stop',
    'abstraction_and_qualities_after_concrete_evidence',
    'author_intent_requires_documentation',
    'avoid_meta_editorial_language_when_it_replaces_observation',
    'authorship_is_transversal_and_tied_to_concrete_decisions',
    'category_appropriate_experience_and_perception_vocabulary',
    'avoid_overexplaining_interpret_only_for_new_relation',
], 'writing_principles keys')
same(writing_principles, dict.fromkeys(writing_principles, True), 'writing_principles values')
for alias in ['concrete_decisions_first', 'evidence_before_abstraction', 'intent_requires_documentation', 'authorship_tied_to_verifiable_decisions']:
    assert alias not in writing_principles, f'forbidden writing-principle alias: {alias}'

article = projection['surfaces']['article']
same(article['title'], {'scope': 'editorial_h1_not_seo_title', 'preferred_max_chars': 60, 'hard_max_chars': 68}, 'article title')
same(article['introduction'], {
    'standard_paragraphs': 2,
    'enforcement': 'preferred',
    'purpose': ['identify_subject', 'establish_relevance', 'establish_editorial_angle', 'orientation_from_design', 'central_design_decisions', 'editorial_direction', 'avoid_editorial_box_repetition'],
    'non_rigid': True,
}, 'article introduction')
same(article['editorial_box'], {
    'required': True,
    'min_words': 40,
    'max_words': 55,
    'must_answer': ['what_it_is', 'who_is_responsible', 'for_whom_when_relevant', 'origin_or_current_state_when_relevant', 'essential_objective_traits'],
    'forbidden': ['links', 'bold', 'promotional_language'],
    'position': 'after_introduction',
    'guardrails': {'primarily_factual': True, 'do_not_repeat_editorial_thesis': True, 'do_not_advance_body_analysis': True},
}, 'editorial_box')
same(article['headings'], {
    'hierarchy_required': True,
    'style': 'descriptive_and_concise',
    'analysis_axis_clear_without_forcing_full_thesis': True,
    'analysis_axis_guidance': 'non_rigid',
    'avoid': 'single_underdeveloped_paragraph',
    'merge_or_expand_when_needed': True,
}, 'headings')
same(article['transitions'], {
    'guidance': 'flexible',
    'use_continuity_when_natural': True,
    'allow_direct_cuts_when_axis_clearly_changes': True,
    'mandatory_bridge': False,
}, 'transitions')
same(article['closing'], {
    'recover_main_design_logic': True,
    'may_connect_to_present_when_supported_by_design': True,
    'prefer_open_or_observational_over_definitive_verdict': True,
}, 'closing')

progressions = {
    'product': ['object/construction', 'form/material', 'body/interaction', 'use/experience', 'space', 'authorship/production/trajectory', 'recognition'],
    'architecture_interiors': ['plan/spatial_operation', 'access', 'route', 'light/materiality/scale', 'everyday_life', 'intimacy', 'authorship/studio'],
    'fashion': ['silhouette/proportion', 'materials/construction', 'body', 'movement', 'perception', 'cultural_or_urban_context', 'authorship'],
    'mobility': ['exterior/proportion', 'access', 'cabin', 'interface/controls', 'driver_vehicle_relation', 'displacement_experience', 'authorship', 'brand_context'],
    'digital_3d': ['novelty_or_hook', 'central_function', 'specific_functions', 'workflow', 'professional_situation_or_utility', 'authorship', 'availability_or_context'],
    'contests_calls': ['what_it_is_and_who_calls', 'eligibility_and_disciplines', 'what_it_seeks_or_criteria', 'prizes_and_value', 'dates_and_deadline', 'how_to_participate', 'real_fit'],
}
for category, expected_progression in progressions.items():
    same(projection['categories'][category]['preferred_progression'], expected_progression, f'{category} preferred_progression')
    same(projection['categories'][category]['non_rigid'], True, f'{category} non_rigid')

print('RC171_CANONICAL_FIDELITY_OK')

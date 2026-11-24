# Competency screens: one string per text area

Status: draft for round 2 (2026-10-19). The owner's rule: every place that shows text has a
language string of its own, never a string shared with another place, so a site can change one
place through Language customisation without changing another. Core strings are not reused even
when the English matches; the theme or plugin key exists so the wording can diverge.

Conventions:

- Theme keys start with `comp_` and name the screen, then the region, then the role:
  `comp_planmap_range_prev`. Plugin keys (the hub and the review desk, if they live in
  `local_dimensions`) follow that plugin's existing prefixes (`central_`, `managecompetencies_`).
- Sentences with a value use `{$a}` or `{$a->name}`; nothing is concatenated in code or templates.
- Plural forms are separate keys (`_one`, `_many`) where the language needs them.
- `lang/en` and `lang/pt_br` move together, sorted, as the fleet rules require.

## Plan map (R2 · Mapa)

| Key | en | pt_br |
|---|---|---|
| comp_planmap_title | Plans map | Mapa dos planos |
| comp_planmap_viewas | View as | Ver como |
| comp_planmap_view_map | Map | Mapa |
| comp_planmap_view_radar | Radar | Radar |
| comp_planmap_view_list | List | Lista |
| comp_planmap_plans_label | Plans on the map | Planos no mapa |
| comp_planmap_highlight_label | Highlight | Destacar |
| comp_planmap_highlight_all | Everything | Tudo |
| comp_planmap_highlight_next | Next steps | Próximos passos |
| comp_planmap_highlight_proficient | Proficient | Proficientes |
| comp_planmap_highlight_unrated | Not rated | Sem avaliação |
| comp_planmap_showlinked | Show courses and activities | Mostrar cursos e atividades |
| comp_planmap_col_area | Area | Área |
| comp_planmap_col_position | {$a->position} of {$a->total} | {$a->position} de {$a->total} |
| comp_planmap_area_count_one | 1 competency | 1 competência |
| comp_planmap_area_count_many | {$a} competencies | {$a} competências |
| comp_planmap_node_unrated | Not rated | Sem avaliação |
| comp_planmap_range_title | Visible range | Faixa visível |
| comp_planmap_range_summary | Competencies {$a->from} to {$a->to} of {$a->total} | Competências {$a->from} a {$a->to} de {$a->total} |
| comp_planmap_range_prev | Previous range | Faixa anterior |
| comp_planmap_range_next | Next range | Próxima faixa |
| comp_planmap_range_strip | All competencies of the chosen plans; choose one to move the range | Todas as competências dos planos escolhidos; escolha uma para mover a faixa |
| comp_planmap_range_legend | Stronger means a higher level | Mais forte = nível mais alto |
| comp_planmap_panel_scale | Scale of this competency | Escala desta competência |
| comp_planmap_panel_scale_text | A {$a->levels}-level scale; proficient from {$a->proficient}. | Escala de {$a->levels} níveis; proficiente a partir de {$a->proficient}. |
| comp_planmap_panel_last | Latest record | Último registro |
| comp_planmap_panel_next | Next steps | Próximos passos |
| comp_planmap_panel_open | Open the competency log | Abrir o diário da competência |

## Radar (R2 · Radar)

| Key | en | pt_br |
|---|---|---|
| comp_radar_axes_label | Axes | Eixos |
| comp_radar_axes_competencies | Competencies | Competências |
| comp_radar_axes_areas | Areas | Áreas |
| comp_radar_ring_proficient | proficient | proficiente |
| comp_radar_ruler_title | One ruler for different scales | Uma régua para escalas diferentes |
| comp_radar_ruler_text | Each axis uses its own scale. The level the scale calls proficient always sits on the dashed ring; lower levels spread up to it and higher ones up to the edge. The ticks on each axis are that scale's levels. | Cada eixo usa a própria escala. O nível que a escala chama de proficiente cai sempre no anel tracejado; os níveis abaixo se distribuem até ele e os de cima até a borda. Os tracinhos em cada eixo são os níveis daquela escala. |
| comp_radar_ruler_levels | {$a} levels | {$a} níveis |
| comp_radar_list_competencies | Competencies on the radar | Competências no radar |
| comp_radar_list_areas | Areas on the radar | Áreas no radar |
| comp_radar_area_summary | {$a->proficient} of {$a->total} proficient | {$a->proficient} de {$a->total} proficientes |
| comp_radar_range_title | Range on the radar | Faixa no radar |
| comp_radar_range_summary | Competencies {$a->from} to {$a->to} of {$a->total} · at most 8 axes at a time | Competências {$a->from} a {$a->to} de {$a->total} · no máximo 8 eixos por vez |
| comp_radar_range_areas | All {$a} competencies, summarised by area | Todas as {$a} competências, resumidas por área |
| comp_radar_alt | Radar with {$a} axes; the dashed ring marks the proficient level of each scale | Radar com {$a} eixos; o anel tracejado marca o nível proficiente de cada escala |

## Competency log (2A, kept from round 1)

| Key | en | pt_br |
|---|---|---|
| comp_log_title | On the journey | Na jornada |
| comp_log_count_one | 1 record | 1 registro |
| comp_log_count_many | {$a} records | {$a} registros |
| comp_log_ahead | From here on | Daqui para frente |
| comp_log_now | You are here: {$a} | Você está aqui: {$a} |
| comp_log_since | Proficient since {$a} | Proficiente desde {$a} |
| comp_log_past | The way so far | O caminho até aqui |
| comp_log_start | Start: plan approved | Início: plano aprovado |
| comp_log_addown | Add your own evidence | Registrar evidência própria |
| comp_scale_title | Where you are on the scale | Onde você está na escala |
| comp_scale_about | About this scale | Sobre esta escala |
| comp_scale_next_optional | The next level exists but is not required. | O próximo degrau existe, mas não é obrigatório. |
| comp_rule_title | How it is earned | Como se conquista |
| comp_rule_points | By points: {$a->earned} of {$a->required} | Por pontos: {$a->earned} de {$a->required} |
| comp_rule_required | required | obrigatória |
| comp_neighbours_title | Neighbourhood | Vizinhança |
| comp_neighbours_text | Related competencies, in the colour and level they have on your map. | Competências relacionadas, com a mesma cor e o mesmo nível que têm no seu mapa. |
| comp_linked_title | Linked content | Conteúdos ligados |

## Review desk (R2 · Revisão)

| Key | en | pt_br |
|---|---|---|
| comp_review_title | Review desk | Mesa de revisão |
| comp_review_tab_competencies | Competencies {$a} | Competências {$a} |
| comp_review_tab_plans | Plans {$a} | Planos {$a} |
| comp_review_filter_waiting | Waiting {$a} | Aguardando {$a} |
| comp_review_filter_mine | In review with me {$a} | Em revisão comigo {$a} |
| comp_review_why_learner | Requested by the learner | Pedido da estudante |
| comp_review_why_course | Recommended on completing the course {$a} | Recomendada ao concluir o curso {$a} |
| comp_review_why_evidence | Sent with their own evidence | Enviada com uma evidência própria |
| comp_review_status_waiting | Waiting for review | Aguardando revisão |
| comp_review_status_mine | In review with you | Em revisão com você |
| comp_review_start | Start review | Iniciar revisão |
| comp_review_stop | Finish review without rating | Encerrar revisão sem avaliar |
| comp_review_since_last | since the last rating | desde a última avaliação |
| comp_review_open_log | Open the full log | Abrir o diário completo |
| comp_review_rate_title | Rate | Avaliar |
| comp_review_rate_scale | Scale "{$a->scale}" · {$a->levels} levels · proficient from {$a->proficient} | Escala "{$a->scale}" · {$a->levels} níveis · proficiente a partir de {$a->proficient} |
| comp_review_rate_current | current | atual |
| comp_review_rate_proficient | proficient | proficiente |
| comp_review_note_label | Rating note | Nota da avaliação |
| comp_review_note_placeholder | What the person showed and what could come next | O que a pessoa mostrou e o que pode vir a seguir |
| comp_review_rate_next | Rate and open the next | Avaliar e abrir o próximo |
| comp_review_rate | Rate | Avaliar |
| comp_review_rate_help | Rating records an evidence and does not change the review state; on rating, the desk finishes the review for you. | Avaliar grava uma evidência e não muda o estado da revisão; ao avaliar, a mesa encerra a revisão por você. |

## Plan workflow (R2 · Fluxo do plano)

| Key | en | pt_br |
|---|---|---|
| comp_flow_state_draft | Draft | Rascunho |
| comp_flow_state_waiting | Waiting for review | Aguardando revisão |
| comp_flow_state_review | In review | Em revisão |
| comp_flow_state_active | Active | Ativo |
| comp_flow_state_complete | Complete | Concluído |
| comp_flow_now | What can be done now | O que pode ser feito agora |
| comp_flow_requestreview | Request review | Pedir revisão |
| comp_flow_requestreview_effect | Moves to "Waiting for review"; reviewers see the request on the review desk. | Vai para "Aguardando revisão"; quem revisa vê o pedido na mesa de revisão. |
| comp_flow_approve_draft | Activate without review | Ativar sem revisão |
| comp_flow_approve_draft_effect | Goes straight to "Active"; the person starts seeing the plan. | Vai direto para "Ativo"; a pessoa passa a ver o plano. |
| comp_flow_startreview | Start review | Iniciar revisão |
| comp_flow_startreview_effect | You become the reviewer; the state becomes "In review". | Você passa a ser quem revisa; o estado fica "Em revisão". |
| comp_flow_cancelrequest | Cancel the request | Cancelar o pedido |
| comp_flow_cancelrequest_effect | Goes back to "Draft". | Volta para "Rascunho". |
| comp_flow_approve_review | Approve and activate | Aprovar e ativar |
| comp_flow_approve_review_effect | Moves to "Active" and ends the review. | Vai para "Ativo" e encerra a revisão. |
| comp_flow_stopreview | Finish review | Encerrar revisão |
| comp_flow_stopreview_effect | Goes back to "Draft" for changes. | Volta para "Rascunho" para ajustes. |
| comp_flow_complete | Complete the plan | Concluir o plano |
| comp_flow_complete_effect | Freezes the rating of each competency as it is today. | Congela as avaliações de cada competência no estado de hoje. |
| comp_flow_unapprove | Back to draft | Voltar a rascunho |
| comp_flow_unapprove_effect | Hidden from the person until it is activated again. | Some da visão da pessoa até ser ativado de novo. |
| comp_flow_reopen | Reopen the plan | Reabrir o plano |
| comp_flow_reopen_effect | Back to "Active"; the frozen snapshot is discarded. | Volta para "Ativo" e descarta o retrato congelado. |
| comp_flow_unlink | Unlink from the template | Desvincular do modelo |
| comp_flow_unlink_effect | The plan becomes the person's own and can be edited. | O plano passa a ser só da pessoa e pode ser editado. |
| comp_flow_templatenote | Created from a template: it does not go through review. | Plano criado a partir de um modelo: não passa por revisão. |

## Course competencies, teacher view (R2 · Curso)

| Key | en | pt_br |
|---|---|---|
| comp_course_title | Competencies of this course | Competências deste curso |
| comp_course_add | Add competencies to the course | Adicionar competências ao curso |
| comp_course_openhub | View in the hub | Ver no Hub |
| comp_course_linkactivity | Link an activity | Ligar atividade |
| comp_course_activity_outcome | on completion: {$a} | ao concluir: {$a} |
| comp_course_outcome_label | On course completion | Ao concluir o curso |
| comp_course_outcome_none | Nothing | Nada |
| comp_course_outcome_evidence | Attach an evidence | Anexar uma evidência |
| comp_course_outcome_recommend | Recommend for review | Recomendar para revisão |
| comp_course_outcome_complete | Mark as complete | Marcar como concluída |
| comp_course_class_unrated | {$a} not rated | {$a} sem avaliação |
| comp_course_class_onway | {$a} on the way | {$a} a caminho |
| comp_course_class_proficient | {$a} proficient | {$a} proficientes |
| comp_course_rateclass | Rate the class | Avaliar a turma |
| comp_course_settings_title | Ratings made in this course | Avaliações feitas neste curso |
| comp_course_settings_plans | Also count in people's plans | Valem também nos planos das pessoas |
| comp_course_settings_course | Count only in this course | Valem só dentro deste curso |

The hub screens (R2 · Hub em nós, R2 · Modelo) keep `local_dimensions`' existing keys and add
their new ones there, under the same one-key-per-place rule.

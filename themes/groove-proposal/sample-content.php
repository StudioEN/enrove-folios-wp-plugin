<?php
/**
 * Sample content for the Groove Proposal theme.
 *
 * Returned to Themes_Manager::get_sample_content() and consumed by the
 * "Add New" folio flow. Keys:
 *   label       Checkbox label shown on the Add New screen.
 *   description Helper copy beneath the checkbox.
 *   subtitle    Value written to the folio's `subtitle` meta.
 *   folio_meta  Extra folio meta written when the seed runs.
 *   pages       Ordered list of ['title', 'content'] folio pages.
 *
 * @package Groove
 */

if (!defined('ABSPATH')) {
  exit;
}

// Resolved from the theme's own folder rather than GROOVE_URL: this file is
// included from Base_Theme::get_sample_content_data(), so static:: binds to the
// theme class and the URL follows the theme wherever it lives. A plugin-relative
// path would 404 for the same theme installed as a package under wp-content.
$sample_logos_url   = static::resolve_theme_folder_url() . 'assets/images/sample-logos/';
$acme_logo_url      = $sample_logos_url . 'acme.svg';
$nordlight_logo_url = $sample_logos_url . 'nordlight.svg';
$vale_logo_url      = $sample_logos_url . 'vale.svg';
$zhou_logo_url      = $sample_logos_url . 'zhou-studio.svg';

return array(
  'label'       => __('Create with sample proposal content', 'groove'),
  'description' => __('Seeds cover details and five sample pages that showcase every proposal content block.', 'groove'),
  'subtitle'    => __('Strategic proposal overview', 'groove'),
  'folio_meta'  => array(
    'proposal_version'       => 'v0.1',
    'proposal_status'        => __('Draft', 'groove'),
    'proposal_prepared_for'  => __('Client Name', 'groove'),
    'proposal_client_name'   => __('Client Name', 'groove'),
    'proposal_prepared_by'   => __('Your Agency Name', 'groove'),
    'proposal_contact_name'  => __('Engagement Lead', 'groove'),
    'proposal_contact_role'  => __('Principal Consultant', 'groove'),
    'proposal_contact_email' => 'hello@example.com',
    'proposal_contact_phone' => '+1 (555) 010-2020',
    'proposal_date'          => wp_date('Y-m-d'),
  ),
  'pages'       => array(
      array(
        'title' => __('Executive Summary', 'groove'),
        'content' => (string) <<<HTML
<!-- wp:heading {"level":2} -->
<h2>Context</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Your team is preparing to scale delivery while improving positioning in a more competitive market. This proposal outlines a focused engagement to align strategy, service narrative, and execution priorities in one practical roadmap.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>We've partnered with organizations facing a similar inflection point, from early-stage teams to established players resetting their story for a new market.</p>
<!-- /wp:paragraph -->
<!-- wp:groove-proposal/logo-strip {"logos":[{"url":"{$acme_logo_url}","name":"Acme Inc.","link":""},{"url":"{$nordlight_logo_url}","name":"Nordlight Group","link":""},{"url":"{$vale_logo_url}","name":"Vale & Co.","link":""},{"url":"{$zhou_logo_url}","name":"Zhou Studio","link":""}]} /-->
<!-- wp:heading {"level":2} -->
<h2>Engagement goals</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>We will clarify your growth priorities, refine your offer architecture, and define operating rhythms that support consistent delivery. The objective is measurable progress in pipeline quality, decision speed, and account confidence.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2} -->
<h2>Expected outcomes</h2>
<!-- /wp:heading -->
<!-- wp:list -->
<ul><li>Sharper positioning and value communication.</li><li>Prioritized delivery plan with ownership.</li><li>Clear implementation milestones for the next 90 days.</li></ul>
<!-- /wp:list -->
<!-- wp:groove-proposal/key-metrics /-->
HTML,
      ),
      array(
        'title' => __('Scope and Approach', 'groove'),
        'content' => (string) <<<HTML
<!-- wp:heading {"level":2} -->
<h2>Workstreams</h2>
<!-- /wp:heading -->
<!-- wp:list -->
<ul><li>Discovery interviews and signal analysis.</li><li>Offer and messaging calibration.</li><li>Delivery model refinement with role clarity.</li></ul>
<!-- /wp:list -->
<!-- wp:groove-proposal/process-steps /-->
<!-- wp:heading {"level":2} -->
<h2>Collaboration model</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>We run weekly working sessions with concise decision memos and a shared action board. Stakeholders receive asynchronous updates between sessions to reduce meeting overhead while preserving momentum.</p>
<!-- /wp:paragraph -->
<!-- wp:groove-proposal/team-grid /-->
<!-- wp:heading {"level":2} -->
<h2>Deliverables</h2>
<!-- /wp:heading -->
<!-- wp:groove-proposal/deliverables /-->
<!-- wp:groove-proposal/callout-box {"title":"A note on scope","body":"This proposal assumes access to existing brand assets and a single point of contact on your side. If either isn't available yet, we'll adjust the Phase 1 timeline together.","style":"important"} /-->
HTML,
      ),
      array(
        'title' => __('Case Studies', 'groove'),
        'content' => (string) <<<HTML
<!-- wp:paragraph -->
<p>The clearest way to evaluate a partner is to see how they've handled a comparable challenge. Here's a recent engagement with a client in a similar position.</p>
<!-- /wp:paragraph -->
<!-- wp:groove-proposal/case-study {"clientName":"Nordlight Group","clientLogo":"{$nordlight_logo_url}","projectTitle":"Repositioning Nordlight for a category shift","tagsText":"Brand, Positioning, Web","stats":[{"value":"3.2x","label":"Qualified pipeline growth"},{"value":"6 weeks","label":"Kickoff to launch"}],"layout":"spotlight"} -->
<!-- wp:heading {"level":3} -->
<h3>The Challenge</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Nordlight had outgrown the positioning that took them to their first major revenue milestone. Every proposal was competing on price because prospects couldn't tell them apart from larger, better-funded competitors.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":3} -->
<h3>Our Approach</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>We ran structured interviews with their best and lost accounts, mapped where Nordlight actually won, and rebuilt the narrative and site around that evidence rather than aspiration.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":3} -->
<h3>The Results</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Within two quarters of launch, the new positioning was showing up directly in sales conversations and win rates.</p>
<!-- /wp:paragraph -->
<!-- wp:groove-proposal/key-metrics {"items":[{"value":"41%","label":"Shorter sales cycle"},{"value":"9","label":"New logos in Q1"}]} /-->
<!-- /wp:groove-proposal/case-study -->
<!-- wp:groove-proposal/testimonial-grid /-->
<!-- wp:groove-proposal/pull-quote /-->
HTML,
      ),
      array(
        'title' => __('Timeline and Investment', 'groove'),
        'content' => (string) <<<HTML
<!-- wp:heading {"level":2} -->
<h2>Timeline</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>The engagement is planned over twelve weeks, structured in four phases with a decision checkpoint at the end of each one.</p>
<!-- /wp:paragraph -->
<!-- wp:groove-proposal/timeline /-->
<!-- wp:heading {"level":2} -->
<h2>Investment</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Project investment is structured as a fixed engagement fee with a staged payment schedule tied to phase completion. Optional follow-on support can be added as a monthly advisory retainer.</p>
<!-- /wp:paragraph -->
<!-- wp:groove-proposal/pricing-table /-->
<!-- wp:heading {"level":2} -->
<h2>Package options</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>If a broader or lighter-touch engagement suits your team better, here's how the tiers compare.</p>
<!-- /wp:paragraph -->
<!-- wp:groove-proposal/comparison-columns /-->
HTML,
      ),
      array(
        'title' => __('Next Steps', 'groove'),
        'content' => (string) <<<HTML
<!-- wp:paragraph -->
<p>Here's what happens once you're ready to move forward, along with answers to the questions we hear most often at this stage.</p>
<!-- /wp:paragraph -->
<!-- wp:groove-proposal/faq /-->
<!-- wp:groove-proposal/cta /-->
HTML,
      ),
  ),
);

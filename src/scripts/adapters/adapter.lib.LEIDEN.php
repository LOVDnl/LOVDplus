<?php
// Leiden specific adapter settings.
$_INSTANCE_CONFIG['columns'] = array(
    'lab_id' => 'Individual/Lab_ID'
);

$_INSTANCE_CONFIG['viewlists']['Screenings_for_I_VE']['cols_to_show'] = array(
    // The screenings data listing on the individual's detailed view.
    // Select these columns for the screenings listing on the individual's page.
    // Note, that you also need to define the hidden columns that
    //  are to be active, since LOVD+ might be filtering on them.
    // You can change the order of columns to any order you like.
    'id',
    'individualid', // Hidden, but needed for search.
    'Screening/Panel_coverage/Fraction',
    'Screening/Father/Panel_coverage/Fraction',
    'Screening/Mother/Panel_coverage/Fraction',
    'curation_progress_',
    'variants_found_',
    'analysis_status',
);

$_INSTANCE_CONFIG['observation_counts'] = array(
    'genepanel' => array(
        'columns' => array(
            'value' => 'Gene Panel',
            'total_individuals' => 'Total # Individuals',
            'percentage' => 'Percentage (%)'
        ),
        'categories' => array(
            'all',
            'gender',
        ),
    ),
    'general' => array(
        // if columns is empty, use default columns list
        'columns' => array(
            'label' => 'Category',
            'value' => 'Value',
            'percentage' => 'Percentage (%)'
        ),
        'categories' => array(
            'all',
            'Individual/Gender',
        ),
        'min_population_size' => 100,
    ),
);

<?php

$BASE_API_URL = 'https://staging.api.mcstats.org/v2';

/**
 * Loads the json object for a plugin
 *
 * @param $pluginName
 * @return mixed
 */
function loadPluginJson($pluginName) {
    global $BASE_API_URL;

    $url = $BASE_API_URL . '/plugin?name=' . urlencode($pluginName);
    $data = file_get_contents($url);
    return json_decode($data);
}

/**
 * Loads the json object for a graph in a plugin
 *
 * @param $pluginId
 * @param $graphName
 * @return mixed
 */
function loadPluginGraphJson($pluginId, $graphName) {
    global $BASE_API_URL;

    $url = $BASE_API_URL . '/plugin/' . $pluginId . '/graph?name=' . urlencode($graphName);
    $data = file_get_contents($url);
    return json_decode($data);
}

/**
 * Loads the json graph data for a given graph
 *
 * @param $pluginId
 * @param $graphId
 * @return mixed
 */
function loadPluginGraphDataJson($pluginId, $graphId) {
    global $BASE_API_URL;

    $url = $BASE_API_URL . '/plugin/' . $pluginId . '/graph/' . $graphId . '/data';
    $data = file_get_contents($url);
    return json_decode($data)->data;
}


abstract class GraphType {

    /**
     * A line graph
     */
    const Line = 0;

    /**
     * An area graph
     */
    const Area = 1;

    /**
     * Column graph
     */
    const Column = 2;

    /**
     * A pie graph
     */
    const Pie = 3;

    /**
     * A percentage area graph
     */
    const Percentage_Area = 4;

    /**
     * A stacked column graph
     */
    const Stacked_Column = 5;

    /**
     * Donut graph
     */
    const Donut = 6;

    /**
     * Geomap
     */
    const Map = 7;

}
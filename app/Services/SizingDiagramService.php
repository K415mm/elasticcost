<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Scenario;

class SizingDiagramService
{
    /**
     * Generate Draw.io XML for all four sizing diagrams.
     */
    public function generateAll(Client $client, Scenario $scenario, array $data): array
    {
        $scenarioName = "{$scenario->name} ({$scenario->description})";

        return [
            'log_ingestion' => $this->generateLogIngestion($data['assets'], $data['totals'], $scenarioName),
            'node_specs' => $this->generateNodeSpecs($data['nodes'], $data['licensing'], $scenarioName),
            'cluster_topology' => $this->generateClusterTopology($data['nodes'], $data['totals'], $scenarioName),
            'node_clustering' => $this->generateNodeClustering($data['nodes'], $scenarioName),
        ];
    }

    /**
     * Diagram 1: Log Ingestion & Source Sizing Breakdown
     */
    private function generateLogIngestion(array $assets, array $totals, string $scenarioName): string
    {
        $xml = $this->getXmlHeader($scenarioName.' — Log Ingestion & Source Sizing');

        // 0 and 1 are root elements
        $id = 2;

        // Title
        $xml .= $this->getmxCellText($id++, 'Log Ingestion & Source Sizing Breakdown', 300, 30, 400, 40, 'fontSize=18;fontStyle=1;fontColor=#3cd2a5;');

        // Log Sources Column
        $xml .= $this->getmxCellText($id++, 'LOG SOURCES', 50, 100, 200, 30, 'fontSize=12;fontStyle=1;fontColor=#94a3b8;align=left;');

        $startY = 140;
        $gapY = 70;
        $sourceIds = [];

        foreach ($assets as $idx => $asset) {
            $y = $startY + ($idx * $gapY);
            $sourceId = $id++;
            $sourceIds[] = $sourceId;

            $labelText = '<b>'.htmlspecialchars($asset['name']).'</b><br>'.
                         'Devices: '.$asset['device_count'].' | '.$asset['total_eps'].' EPS<br>'.
                         'Raw Daily: '.$asset['daily_raw_gb'].' GB/day';

            $xml .= $this->getmxCellBox(
                $sourceId,
                $labelText,
                50, $y, 220, 50,
                'rounded=1;fillColor=#1e293b;strokeColor=#475569;fontColor=#e2e8f0;align=center;'
            );
        }

        // Ingestion & Processing Hub (Middle)
        $hubId = $id++;
        $hubText = '<b>INGESTION ENGINE</b><br>Logstash / Elastic Agent<br>Daily Processing:<br>'.$totals['daily_raw_gb'].' GB/day';
        $xml .= $this->getmxCellBox(
            $hubId,
            $hubText,
            380, 200, 180, 80,
            'rounded=1;fillColor=#0f766e;strokeColor=#14b8a6;fontColor=#ffffff;align=center;fontStyle=1;'
        );

        // Connect Sources to Hub
        foreach ($sourceIds as $sId) {
            $xml .= $this->getmxCellEdge($id++, $sId, $hubId);
        }

        // Elasticsearch Destination (Right)
        $destId = $id++;
        $destText = '<b>ELASTICSEARCH CLUSTER</b><br>Daily Indexed: '.$totals['daily_indexed_gb'].' GB<br>Daily Ingested (+Replica): '.$totals['daily_ingested_gb'].' GB';
        $xml .= $this->getmxCellBox(
            $destId,
            $destText,
            680, 200, 220, 80,
            'rounded=1;fillColor=#1e1b4b;strokeColor=#4f46e5;fontColor=#ffffff;align=center;fontStyle=1;'
        );

        // Connect Hub to Elasticsearch
        $xml .= $this->getmxCellEdge($id++, $hubId, $destId);

        $xml .= $this->getXmlFooter();

        return $xml;
    }

    /**
     * Diagram 2: Recommended Node Specs
     */
    private function generateNodeSpecs(array $nodes, array $licensing, string $scenarioName): string
    {
        $xml = $this->getXmlHeader($scenarioName.' — Recommended Node Specs');
        $id = 2;

        // Title
        $xml .= $this->getmxCellText($id++, 'Recommended Node Specifications', 300, 30, 400, 40, 'fontSize=18;fontStyle=1;fontColor=#3cd2a5;');

        // Headers
        $headers = ['Node Type / Role', 'Count', 'RAM / Node', 'JVM Heap', 'Storage / Node', 'Storage Type'];
        $colWidths = [240, 80, 120, 100, 140, 140];
        $startX = 50;
        $y = 100;

        // Render header row
        $x = $startX;
        foreach ($headers as $cIdx => $header) {
            $xml .= $this->getmxCellBox(
                $id++,
                '<b>'.$header.'</b>',
                $x, $y, $colWidths[$cIdx], 35,
                'fillColor=#1e293b;strokeColor=#475569;fontColor=#ffffff;align=center;'
            );
            $x += $colWidths[$cIdx];
        }

        // Render nodes rows
        foreach ($nodes as $rIdx => $node) {
            $y += 35;
            $x = $startX;

            $storageStr = $node['storage_gb'] >= 1000 ? ($node['storage_gb'] / 1000).' TB' : $node['storage_gb'].' GB';

            $rowValues = [
                $node['name'].' ('.$node['role'].')',
                $node['count'],
                $node['ram_gb'].' GB',
                $node['heap_gb'].' GB',
                $storageStr,
                $node['storage_type'],
            ];

            $zebraStyle = ($rIdx % 2 === 1) ? 'fillColor=#1e293b;bgOpacity=0.5;' : 'fillColor=none;';

            foreach ($rowValues as $cIdx => $val) {
                $align = ($cIdx === 0 || $cIdx === 5) ? 'align=left;' : 'align=center;';
                $xml .= $this->getmxCellBox(
                    $id++,
                    htmlspecialchars($val),
                    $x, $y, $colWidths[$cIdx], 35,
                    "strokeColor=#334155;fontColor=#e2e8f0;{$align}{$zebraStyle}"
                );
                $x += $colWidths[$cIdx];
            }
        }

        // Licensing Summary Box below the table
        $y += 70;
        $summaryText = '<b>LICENSING & RESOURCE SUMMARY</b><br>'.
                       'Total Cluster Memory: <b>'.$licensing['total_ram_gb'].' GB RAM</b><br>'.
                       'Required Elastic Resource Units (ERUs): <b>'.$licensing['required_erus'].' ERUs</b> (at 64 GB per ERU)<br>'.
                       'Projected Annual License Cost: <b>$'.number_format($licensing['annual_cost_usd']).' USD</b>';

        $xml .= $this->getmxCellBox(
            $id++,
            $summaryText,
            50, $y, 820, 90,
            'rounded=1;fillColor=#1e1b4b;strokeColor=#4f46e5;fontColor=#ffffff;align=left;spacingLeft=15;'
        );

        $xml .= $this->getXmlFooter();

        return $xml;
    }

    /**
     * Diagram 3: Cluster Topology Editor
     */
    private function generateClusterTopology(array $nodes, array $totals, string $scenarioName): string
    {
        $xml = $this->getXmlHeader($scenarioName.' — Cluster Topology Editor');
        $id = 2;

        // Title
        $xml .= $this->getmxCellText($id++, 'Cluster Storage Lifecycle & Node Topology', 300, 30, 400, 40, 'fontSize=18;fontStyle=1;fontColor=#3cd2a5;');

        // Storage details overview on the left
        $overviewText = '<b>DATA LIFECYCLE TIERS</b><br><br>'.
                        '• Hot Storage: <b>'.number_format($totals['hot_storage_gb'], 2).' GB</b><br>'.
                        '• Warm Storage: <b>'.number_format($totals['warm_storage_gb'], 2).' GB</b><br>'.
                        '• Cold Storage: <b>'.number_format($totals['cold_storage_gb'], 2).' GB</b><br>'.
                        '• Frozen Storage: <b>'.number_format($totals['frozen_storage_gb'], 2).' GB</b><br><br>'.
                        '<b>Total Physical Footprint: '.number_format($totals['total_storage_footprint_gb'], 2).' GB</b>';

        $xml .= $this->getmxCellBox(
            $id++,
            $overviewText,
            50, 100, 240, 180,
            'rounded=1;fillColor=#0f172a;strokeColor=#334155;fontColor=#e2e8f0;align=left;spacingLeft=10;'
        );

        // Group / Container of recommended nodes
        $xml .= $this->getmxCellBox(
            $id++,
            '<b>RECOMMENDED TOPOLOGY LAYOUT</b>',
            340, 100, 530, 35,
            'fillColor=#1e293b;strokeColor=#475569;fontColor=#ffffff;align=center;fontStyle=1;'
        );

        $startY = 150;
        foreach ($nodes as $idx => $node) {
            $y = $startY + ($idx * 75);

            $storageStr = $node['storage_gb'] >= 1000 ? ($node['storage_gb'] / 1000).' TB' : $node['storage_gb'].' GB';

            // Choose color style based on node role
            $colorStyle = 'fillColor=#1e293b;strokeColor=#475569;';
            if (str_contains(strtolower($node['role']), 'hot')) {
                $colorStyle = 'fillColor=#7f1d1d;strokeColor=#f87171;';
            } elseif (str_contains(strtolower($node['role']), 'warm')) {
                $colorStyle = 'fillColor=#7c2d12;strokeColor=#fb923c;';
            } elseif (str_contains(strtolower($node['role']), 'cold')) {
                $colorStyle = 'fillColor=#1e3a8a;strokeColor=#60a5fa;';
            } elseif (str_contains(strtolower($node['role']), 'frozen')) {
                $colorStyle = 'fillColor=#134e5e;strokeColor=#22d3ee;';
            }

            $labelText = '<b>'.htmlspecialchars($node['name']).'</b> (Count: '.$node['count'].')<br>'.
                         'Role: '.htmlspecialchars($node['role']).' | RAM: '.$node['ram_gb'].' GB<br>'.
                         'Disk: '.$storageStr.' ('.htmlspecialchars($node['storage_type']).')';

            $xml .= $this->getmxCellBox(
                $id++,
                $labelText,
                340, $y, 530, 60,
                "rounded=1;fontColor=#ffffff;align=left;spacingLeft=15;{$colorStyle}"
            );
        }

        $xml .= $this->getXmlFooter();

        return $xml;
    }

    /**
     * Diagram 4: Recommended Node Clustering Topology
     */
    private function generateNodeClustering(array $nodes, string $scenarioName): string
    {
        $xml = $this->getXmlHeader($scenarioName.' — Node Clustering Topology');
        $id = 2;

        // Title
        $xml .= $this->getmxCellText($id++, 'Recommended Node Clustering Topology', 300, 30, 400, 40, 'fontSize=18;fontStyle=1;fontColor=#3cd2a5;');

        // We will lay out the nodes horizontally or in a nice cluster layout.
        // E.g. Master nodes in the center, and Data nodes surrounding them.

        $masterNodes = [];
        $dataNodes = [];

        foreach ($nodes as $node) {
            if (str_contains(strtolower($node['role']), 'master') && ! str_contains(strtolower($node['role']), 'data')) {
                $masterNodes[] = $node;
            } else {
                $dataNodes[] = $node;
            }
        }

        // If no dedicated master nodes, let's group all of them.
        if (empty($masterNodes)) {
            $dataNodes = $nodes;
        }

        $centerId = $id++;
        $xml .= $this->getmxCellBox(
            $centerId,
            '<b>CLUSTER MANAGER</b><br>Quorum / Master eligible',
            380, 100, 180, 60,
            'rounded=1;fillColor=#1e293b;strokeColor=#475569;fontColor=#ffffff;align=center;'
        );

        // Position nodes in a semi-circle or grid below the cluster manager
        $startX = 100;
        $gapX = 250;
        $y = 240;

        foreach ($dataNodes as $idx => $node) {
            $x = $startX + ($idx * $gapX);
            $nodeId = $id++;

            $storageStr = $node['storage_gb'] >= 1000 ? ($node['storage_gb'] / 1000).' TB' : $node['storage_gb'].' GB';

            // Colors based on roles
            $colorStyle = 'fillColor=#1e293b;strokeColor=#475569;';
            if (str_contains(strtolower($node['role']), 'hot')) {
                $colorStyle = 'fillColor=#7f1d1d;strokeColor=#f87171;';
            } elseif (str_contains(strtolower($node['role']), 'warm')) {
                $colorStyle = 'fillColor=#7c2d12;strokeColor=#fb923c;';
            } elseif (str_contains(strtolower($node['role']), 'cold')) {
                $colorStyle = 'fillColor=#1e3a8a;strokeColor=#60a5fa;';
            } elseif (str_contains(strtolower($node['role']), 'frozen')) {
                $colorStyle = 'fillColor=#134e5e;strokeColor=#22d3ee;';
            }

            $labelText = '<b>'.htmlspecialchars($node['name']).'</b><br>'.
                         'Role: '.htmlspecialchars($node['role']).'<br>'.
                         'Count: '.$node['count'].' | RAM: '.$node['ram_gb'].' GB<br>'.
                         'Disk: '.$storageStr;

            $xml .= $this->getmxCellBox(
                $nodeId,
                $labelText,
                $x, $y, 200, 80,
                "rounded=1;fontColor=#ffffff;align=center;{$colorStyle}"
            );

            // Connect this node to the manager
            $xml .= $this->getmxCellEdge($id++, $nodeId, $centerId);
        }

        $xml .= $this->getXmlFooter();

        return $xml;
    }

    /**
     * XML Helpers
     */
    private function getXmlHeader(string $name): string
    {
        $escapedName = htmlspecialchars($name, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return "<mxfile host=\"Embed\" modified=\"2026-07-15T00:00:00.000Z\" agent=\"ElasticCost App\" version=\"21.0.0\" type=\"embed\">
  <diagram id=\"default-diagram-id\" name=\"{$escapedName}\">
    <mxGraphModel dx=\"1000\" dy=\"1000\" grid=\"1\" gridSize=\"10\" guides=\"1\" tooltips=\"1\" connect=\"1\" arrows=\"1\" fold=\"1\" page=\"1\" pageScale=\"1\" pageWidth=\"1000\" pageHeight=\"800\" math=\"0\" shadow=\"0\">
      <root>
        <mxCell id=\"0\" />
        <mxCell id=\"1\" parent=\"0\" />";
    }

    private function getXmlFooter(): string
    {
        return '      </root>
    </mxGraphModel>
  </diagram>
</mxfile>';
    }

    private function getmxCellText(int $id, string $value, int $x, int $y, int $w, int $h, string $style = ''): string
    {
        $escapedVal = htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return "\n        <mxCell id=\"{$id}\" value=\"{$escapedVal}\" style=\"text;html=1;strokeColor=none;fillColor=none;align=center;verticalAlign=middle;whiteSpace=wrap;rounded=0;{$style}\" vertex=\"1\" parent=\"1\">
          <mxGeometry x=\"{$x}\" y=\"{$y}\" width=\"{$w}\" height=\"{$h}\" as=\"geometry\" />
        </mxCell>";
    }

    private function getmxCellBox(int $id, string $value, int $x, int $y, int $w, int $h, string $style = ''): string
    {
        $escapedVal = htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return "\n        <mxCell id=\"{$id}\" value=\"{$escapedVal}\" style=\"whiteSpace=wrap;html=1;{$style}\" vertex=\"1\" parent=\"1\">
          <mxGeometry x=\"{$x}\" y=\"{$y}\" width=\"{$w}\" height=\"{$h}\" as=\"geometry\" />
        </mxCell>";
    }

    private function getmxCellEdge(int $id, int $sourceId, int $targetId, string $style = ''): string
    {
        return "\n        <mxCell id=\"{$id}\" style=\"edgeStyle=orthogonalEdgeStyle;rounded=0;orthogonalLoop=1;jettySize=auto;html=1;strokeColor=#94a3b8;strokeWidth=2;endArrow=classic;endFill=1;{$style}\" edge=\"1\" parent=\"1\" source=\"{$sourceId}\" target=\"{$targetId}\">
          <mxGeometry relative=\"1\" as=\"geometry\" />
        </mxCell>";
    }
}

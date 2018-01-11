<?php
namespace Ttree\CrossReference\Command;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Cli\CommandController;
use Neos\Neos\Controller\CreateContentContextTrait;

class CrossReferenceCommandController extends CommandController
{
    use CreateContentContextTrait;

    public function triggerCommand(string $identifier, string $property)
    {
        $context = $this->createContentContext('live');
        $node = $context->getNodeByIdentifier($identifier);
        if ($node === null) {
            $this->outputLine('Node not found');
            $this->quit(1);
        }
        $this->trigger($node, $property);
    }

    public function batchTriggerCommand(string $rootNode, string $filter, string $property)
    {
        $context = $this->createContentContext('live');
        $rootNode = $context->getNodeByIdentifier($rootNode);
        if ($rootNode === null) {
            $this->outputLine('Node not found');
            $this->quit(1);
        }
        $query = (new FlowQuery([$rootNode]))->find($filter);
        foreach ($query as $node) {
            $this->trigger($node, $property);
        }
    }

    protected function trigger(NodeInterface $node, string $property)
    {
        $this->outputLine('Process node <b>%s</b> with property "%s"', [$node->getLabel(), $property]);
        $value = $node->getProperty($property);
        if ($value !== null && $value !== []) {
            $this->outputLine(' Reference(s) count: %d', [count($value)]);
            $node->setProperty($property, $value);
        } else {
            $this->outputLine(' <comment>Empty reference</comment>');
        }
    }
}

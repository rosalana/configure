<?php

use Rosalana\Configure\Node\Section;
use Rosalana\Configure\Node\Value;

// Helper: create a section with N anonymous values already added and reflowed
function sectionWith(int $count): array
{
    $section = Section::makeEmpty('parent');
    $values = [];

    for ($i = 0; $i < $count; $i++) {
        $v = Value::makeEmpty("key_{$i}");
        $values[] = $v;
        $section->addChild($v, true); // ghost, batch reflow at the end
    }

    $section->reflow();

    return [$section, ...$values];
}

describe('parent-child relationships', function () {

    it('sets the parent reference when a child is added', function () {
        $section = Section::makeEmpty('parent');
        $v = Value::makeEmpty('child');

        $section->addChild($v);

        expect($v->parent())->toBe($section);
    });

    it('reports child as present after adding', function () {
        $section = Section::makeEmpty('parent');
        $v = Value::makeEmpty('child');

        $section->addChild($v);

        expect($section->hasChild($v))->toBeTrue();
    });

    it('reports child as absent after removing', function () {
        $section = Section::makeEmpty('parent');
        $v = Value::makeEmpty('child');

        $section->addChild($v);
        $section->removeChild($v);

        expect($section->hasChild($v))->toBeFalse();
    });

    it('knows whether it is the first or last child', function () {
        [$section, $v1, $v2, $v3] = sectionWith(3);

        expect($v1->isFirstChild())->toBeTrue();
        expect($v3->isLastChild())->toBeTrue();
        expect($v2->isFirstChild())->toBeFalse();
        expect($v2->isLastChild())->toBeFalse();
    });
});

describe('sibling queries', function () {

    it('returns nodes before and after correctly', function () {
        [$section, $v1, $v2, $v3] = sectionWith(3);

        expect($v2->siblingsBefore()->contains($v1))->toBeTrue();
        expect($v2->siblingsAfter()->contains($v3))->toBeTrue();
    });

    it('does not include itself in siblings', function () {
        [$section, $v1, $v2] = sectionWith(2);

        expect($v1->siblings()->contains($v1))->toBeFalse();
    });

    it('recognises when a node is sibling of another', function () {
        [$section, $v1, $v2] = sectionWith(2);

        expect($v1->isSiblingOf($v2))->toBeTrue();
    });
});

describe('position management via reflow', function () {

    it('assigns increasing start positions to children after reflow', function () {
        [$section, $v1, $v2, $v3] = sectionWith(3);

        expect($v1->start())->toBeLessThan($v2->start());
        expect($v2->start())->toBeLessThan($v3->start());
    });

    it('closing the gap: subsequent sibling moves up after a node is removed', function () {
        [$section, $v1, $v2, $v3] = sectionWith(3);

        $v3StartBefore = $v3->start();

        $v2->remove();

        expect($v3->start())->toBeLessThan($v3StartBefore);
    });

    it('section end expands when a new child is added', function () {
        $section = Section::makeEmpty('parent');
        $endBefore = $section->end();

        $section->addChild(Value::makeEmpty('child'));

        expect($section->end())->toBeGreaterThan($endBefore);
    });

    it('section end shrinks when a child is removed', function () {
        [$section, $v1, $v2] = sectionWith(2);

        $endBefore = $section->end();
        $v2->remove();

        expect($section->end())->toBeLessThan($endBefore);
    });
});

describe('node swapping', function () {

    it('reverses the order of two children after swap', function () {
        [$section, $v1, $v2] = sectionWith(2);

        expect($v1->start())->toBeLessThan($v2->start());

        $section->swapChildren($v1, $v2);

        // v2 is now physically before v1
        expect($v2->start())->toBeLessThan($v1->start());
    });
});

describe('node dirty state', function () {

    it('a freshly created node is always dirty', function () {
        expect(Value::makeEmpty('test')->isDirty())->toBeTrue();
        expect(Section::makeEmpty('test')->isDirty())->toBeTrue();
    });
});

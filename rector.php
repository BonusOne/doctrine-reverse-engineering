<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Assign\CombinedAssignRector;
use Rector\CodeQuality\Rector\Catch_\ThrowWithPreviousExceptionRector;
use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\CodeQuality\Rector\ClassMethod\InlineArrayReturnAssignRector;
use Rector\CodeQuality\Rector\ClassMethod\OptionalParametersAfterRequiredRector;
use Rector\CodeQuality\Rector\Concat\JoinStringConcatRector;
use Rector\CodeQuality\Rector\For_\ForRepeatedCountToOwnVariableRector;
use Rector\CodeQuality\Rector\FuncCall\ArrayMergeOfNonArraysToSimpleArrayRector;
use Rector\CodeQuality\Rector\FuncCall\ChangeArrayPushToArrayAssignRector;
use Rector\CodeQuality\Rector\Identical\BooleanNotIdenticalToNotIdenticalRector;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\CodeQuality\Rector\If_\CombineIfRector;
use Rector\CodeQuality\Rector\If_\ConsecutiveNullCompareReturnsToNullCoalesceQueueRector;
use Rector\CodeQuality\Rector\If_\ExplicitBoolCompareRector;
use Rector\CodeQuality\Rector\Include_\AbsolutizeRequireAndIncludePathRector;
use Rector\CodeQuality\Rector\LogicalAnd\AndAssignsToSeparateLinesRector;
use Rector\CodeQuality\Rector\NotEqual\CommonNotEqualRector;
use Rector\CodeQuality\Rector\Ternary\ArrayKeyExistsTernaryThenValueToCoalescingRector;
use Rector\CodingStyle\Rector\Assign\SplitDoubleAssignRector;
use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\CodingStyle\Rector\ClassMethod\NewlineBeforeNewAssignSetRector;
use Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector;
use Rector\CodingStyle\Rector\FuncCall\ConsistentImplodeRector;
use Rector\CodingStyle\Rector\Property\SplitGroupedPropertiesRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Assign\RemoveDoubleAssignRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodRector;
use Rector\DeadCode\Rector\For_\RemoveDeadContinueRector;
use Rector\DeadCode\Rector\For_\RemoveDeadIfForeachForRector;
use Rector\DeadCode\Rector\For_\RemoveDeadLoopRector;
use Rector\DeadCode\Rector\FunctionLike\RemoveDeadReturnRector;
use Rector\DeadCode\Rector\Switch_\RemoveDuplicatedCaseInSwitchRector;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\EarlyReturn\Rector\If_\RemoveAlwaysElseRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\ConstructClassMethodToSetUpTestCaseRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertComparisonToSpecificMethodRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertEqualsToSameRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertFalseStrposToContainsRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertInstanceOfComparisonRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertIssetToSpecificMethodRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertNotOperatorRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertPropertyExistsRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertRegExpRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertSameBoolNullToSpecificMethodRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertSameTrueFalseToAssertTrueFalseRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertTrueFalseToSpecificMethodRector;
use Rector\PHPUnit\PHPUnit100\Rector\Class_\AddProphecyTraitRector;
use Rector\PHPUnit\PHPUnit50\Rector\StaticCall\GetMockRector;
use Rector\PHPUnit\PHPUnit60\Rector\ClassMethod\AddDoesNotPerformAssertionToNonAssertingTestRector;
use Rector\PHPUnit\PHPUnit60\Rector\ClassMethod\ExceptionAnnotationRector;
use Rector\PHPUnit\PHPUnit60\Rector\MethodCall\GetMockBuilderGetMockToCreateMockRector;
use Rector\PHPUnit\PHPUnit70\Rector\Class_\RemoveDataProviderTestPrefixRector;
use Rector\PHPUnit\PHPUnit80\Rector\MethodCall\AssertEqualsParameterToSpecificMethodsTypeRector;
use Rector\PHPUnit\PHPUnit80\Rector\MethodCall\SpecificAssertInternalTypeRector;
use Rector\PHPUnit\PHPUnit90\Rector\MethodCall\ExplicitPhpErrorApiRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\CodeQuality\Rector\BinaryOp\ResponseStatusCodeRector;
use Rector\Symfony\CodeQuality\Rector\ClassMethod\ActionSuffixRemoverRector;
use Rector\Symfony\CodeQuality\Rector\ClassMethod\ParamTypeFromRouteRequiredRegexRector;
use Rector\Symfony\Set\SensiolabsSetList;
use Rector\Symfony\Set\SymfonySetList;
use Rector\Symfony\Symfony26\Rector\MethodCall\RedirectToRouteRector;
use Rector\Symfony\Symfony28\Rector\MethodCall\GetToConstructorInjectionRector;
use Rector\Symfony\Symfony30\Rector\ClassMethod\GetRequestRector;
use Rector\Symfony\Symfony34\Rector\ClassMethod\RemoveServiceFromSensioRouteRector;
use Rector\Symfony\Symfony40\Rector\MethodCall\FormIsValidRector;
use Rector\Symfony\Symfony42\Rector\MethodCall\ContainerGetToConstructorInjectionRector;
use Rector\Symfony\Symfony43\Rector\MethodCall\MakeDispatchFirstArgumentEventRector;
use Rector\Symfony\Symfony43\Rector\MethodCall\WebTestCaseAssertIsSuccessfulRector;
use Rector\Symfony\Symfony43\Rector\MethodCall\WebTestCaseAssertResponseCodeRector;
use Rector\Symfony\Symfony44\Rector\ClassMethod\ConsoleExecuteReturnIntRector;
use Rector\Symfony\Symfony51\Rector\ClassMethod\CommandConstantReturnCodeRector;
use Rector\Symfony\Symfony52\Rector\StaticCall\BinaryFileResponseCreateToNewInstanceRector;
use Rector\Symfony\Symfony53\Rector\StaticPropertyFetch\KernelTestCaseContainerPropertyDeprecationRector;
use Rector\Transform\Rector\Attribute\AttributeKeyToClassConstFetchRector;
use Rector\ValueObject\PhpVersion;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__.'/src',
    ]);

    // $rectorConfig->symfonyContainerXml(__DIR__.'/var/cache/dev/App_KernelDevDebugContainer.xml');

    // register a single rule
    $rectorConfig->rule(AbsolutizeRequireAndIncludePathRector::class);
    $rectorConfig->rule(AndAssignsToSeparateLinesRector::class);
    $rectorConfig->rule(ArrayKeyExistsTernaryThenValueToCoalescingRector::class);
    $rectorConfig->rule(ArrayMergeOfNonArraysToSimpleArrayRector::class);
    $rectorConfig->rule(BooleanNotIdenticalToNotIdenticalRector::class);
    $rectorConfig->rule(ChangeArrayPushToArrayAssignRector::class);
    $rectorConfig->rule(CombineIfRector::class);
    $rectorConfig->rule(CombinedAssignRector::class);
    $rectorConfig->rule(CommonNotEqualRector::class);
    $rectorConfig->rule(ConsecutiveNullCompareReturnsToNullCoalesceQueueRector::class);
    $rectorConfig->rule(ExplicitBoolCompareRector::class);
    $rectorConfig->rule(FlipTypeControlToUseExclusiveTypeRector::class);
    $rectorConfig->rule(ForRepeatedCountToOwnVariableRector::class);
    $rectorConfig->rule(InlineArrayReturnAssignRector::class);
    $rectorConfig->rule(InlineConstructorDefaultToPropertyRector::class);
    $rectorConfig->rule(JoinStringConcatRector::class);
    $rectorConfig->rule(OptionalParametersAfterRequiredRector::class);
    $rectorConfig->rule(ThrowWithPreviousExceptionRector::class);
    $rectorConfig->rule(CatchExceptionNameMatchingTypeRector::class);
    $rectorConfig->rule(ConsistentImplodeRector::class);
    $rectorConfig->rule(EncapsedStringsToSprintfRector::class);
    $rectorConfig->rule(NewlineBeforeNewAssignSetRector::class);
    $rectorConfig->rule(SplitDoubleAssignRector::class);
    $rectorConfig->rule(SplitGroupedPropertiesRector::class);
    $rectorConfig->rule(RemoveDeadContinueRector::class);
    $rectorConfig->rule(RemoveDeadIfForeachForRector::class);
    $rectorConfig->rule(RemoveDeadLoopRector::class);
    $rectorConfig->rule(RemoveDeadReturnRector::class);
    $rectorConfig->rule(RemoveDoubleAssignRector::class);
    $rectorConfig->rule(RemoveDuplicatedCaseInSwitchRector::class);
    $rectorConfig->rule(RemoveAlwaysElseRector::class);

    /* SYMFONY */
    $rectorConfig->rule(ActionSuffixRemoverRector::class);
    $rectorConfig->rule(BinaryFileResponseCreateToNewInstanceRector::class);
    $rectorConfig->rule(CommandConstantReturnCodeRector::class);
    $rectorConfig->rule(ConsoleExecuteReturnIntRector::class);
    $rectorConfig->rule(ContainerGetToConstructorInjectionRector::class);
    $rectorConfig->rule(FormIsValidRector::class);
    $rectorConfig->rule(GetRequestRector::class);
    $rectorConfig->rule(GetToConstructorInjectionRector::class);
    $rectorConfig->rule(KernelTestCaseContainerPropertyDeprecationRector::class);
    $rectorConfig->rule(MakeDispatchFirstArgumentEventRector::class);
    $rectorConfig->rule(ParamTypeFromRouteRequiredRegexRector::class);
    $rectorConfig->rule(RemoveServiceFromSensioRouteRector::class);
    $rectorConfig->rule(ResponseStatusCodeRector::class);
    $rectorConfig->rule(WebTestCaseAssertIsSuccessfulRector::class);
    $rectorConfig->rule(WebTestCaseAssertResponseCodeRector::class);

    /* PHPUNIT */
    $rectorConfig->rule(AddDoesNotPerformAssertionToNonAssertingTestRector::class);
    $rectorConfig->rule(AddProphecyTraitRector::class);
    $rectorConfig->rule(AssertComparisonToSpecificMethodRector::class);
    $rectorConfig->rule(AssertEqualsParameterToSpecificMethodsTypeRector::class);
    $rectorConfig->rule(AssertEqualsToSameRector::class);
    $rectorConfig->rule(AssertFalseStrposToContainsRector::class);
    $rectorConfig->rule(AssertInstanceOfComparisonRector::class);
    $rectorConfig->rule(AssertIssetToSpecificMethodRector::class);
    $rectorConfig->rule(AssertNotOperatorRector::class);
    $rectorConfig->rule(AssertPropertyExistsRector::class);
    $rectorConfig->rule(AssertRegExpRector::class);
    $rectorConfig->rule(AssertSameBoolNullToSpecificMethodRector::class);
    $rectorConfig->rule(AssertSameTrueFalseToAssertTrueFalseRector::class);
    $rectorConfig->rule(AssertTrueFalseToSpecificMethodRector::class);
    $rectorConfig->rule(ConstructClassMethodToSetUpTestCaseRector::class);
    $rectorConfig->rule(ExceptionAnnotationRector::class);
    $rectorConfig->rule(ExplicitPhpErrorApiRector::class);
    $rectorConfig->rule(GetMockBuilderGetMockToCreateMockRector::class);
    $rectorConfig->rule(GetMockRector::class);
    $rectorConfig->rule(RemoveDataProviderTestPrefixRector::class);
    $rectorConfig->rule(SpecificAssertInternalTypeRector::class);

    // define sets of rules
    $rectorConfig->import(LevelSetList::UP_TO_PHP_82);

    $rectorConfig->phpVersion(PhpVersion::PHP_82);

    $rectorConfig->sets([
        DoctrineSetList::DOCTRINE_CODE_QUALITY,
        DoctrineSetList::DOCTRINE_ORM_214,
        DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
        DoctrineSetList::GEDMO_ANNOTATIONS_TO_ATTRIBUTES,
        SetList::DEAD_CODE,
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::PHP_82,
        SetList::TYPE_DECLARATION,
        SymfonySetList::SYMFONY_64,
        SymfonySetList::SYMFONY_71,
        SymfonySetList::SYMFONY_CODE_QUALITY,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
        SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES,
        SensiolabsSetList::ANNOTATIONS_TO_ATTRIBUTES,
    ]);

    $rectorConfig->skip([
        AttributeKeyToClassConstFetchRector::class,
        ExplicitBoolCompareRector::class,
        RemoveUnusedPrivateMethodRector::class,
        RedirectToRouteRector::class,
    ]);
};

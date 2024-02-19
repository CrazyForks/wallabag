<?php

namespace Wallabag\Controller\Api;

use Nelmio\ApiDocBundle\Annotation\Operation;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Wallabag\Entity\Entry;
use Wallabag\Entity\Tag;
use Wallabag\Repository\EntryRepository;
use Wallabag\Repository\TagRepository;

class TagRestController extends WallabagRestController
{
    /**
     * Retrieve all tags.
     *
     * @Operation(
     *     tags={"Tags"},
     *     summary="Retrieve all tags.",
     *     @OA\Response(
     *         response="200",
     *         description="Returned when successful"
     *     )
     * )
     *
     * @Route("/api/tags.{_format}", methods={"GET"}, name="api_get_tags", defaults={"_format": "json"})
     *
     * @return JsonResponse
     */
    public function getTagsAction(TagRepository $tagRepository)
    {
        $this->validateAuthentication();

        $tags = $tagRepository->findAllFlatTagsWithNbEntries($this->getUser()->getId());

        $json = $this->serializer->serialize($tags, 'json');

        return (new JsonResponse())->setJson($json);
    }

    /**
     * Permanently remove one tag from **every** entry by passing the Tag label.
     *
     * @Operation(
     *     tags={"Tags"},
     *     summary="Permanently remove one tag from every entry by passing the Tag label.",
     *     @OA\Parameter(
     *         name="tag",
     *         in="query",
     *         description="Tag as a string",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             pattern="\w+",
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returned when successful"
     *     )
     * )
     *
     * @Route("/api/tag/label.{_format}", methods={"DELETE"}, name="api_delete_tag_label", defaults={"_format": "json"})
     *
     * @return JsonResponse
     */
    public function deleteTagLabelAction(Request $request, TagRepository $tagRepository, EntryRepository $entryRepository)
    {
        $this->validateAuthentication();
        $label = $request->get('tag', '');

        $tags = $tagRepository->findByLabelsAndUser([$label], $this->getUser()->getId());

        if (empty($tags)) {
            throw $this->createNotFoundException('Tag not found');
        }

        $tag = $tags[0];

        $entryRepository->removeTag($this->getUser()->getId(), $tag);

        $this->cleanOrphanTag($tag);

        $json = $this->serializer->serialize($tag, 'json');

        return (new JsonResponse())->setJson($json);
    }

    /**
     * Permanently remove some tags from **every** entry.
     *
     * @Operation(
     *     tags={"Tags"},
     *     summary="Permanently remove some tags from every entry.",
     *     @OA\Parameter(
     *         name="tags",
     *         in="query",
     *         description="Tags as strings (comma splitted)",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             example="tag1,tag2",
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returned when successful"
     *     )
     * )
     *
     * @Route("/api/tags/label.{_format}", methods={"DELETE"}, name="api_delete_tags_label", defaults={"_format": "json"})
     *
     * @return JsonResponse
     */
    public function deleteTagsLabelAction(Request $request, TagRepository $tagRepository, EntryRepository $entryRepository)
    {
        $this->validateAuthentication();

        $tagsLabels = $request->get('tags', '');

        $tags = $tagRepository->findByLabelsAndUser(explode(',', $tagsLabels), $this->getUser()->getId());

        if (empty($tags)) {
            throw $this->createNotFoundException('Tags not found');
        }

        $entryRepository->removeTags($this->getUser()->getId(), $tags);

        $this->cleanOrphanTag($tags);

        $json = $this->serializer->serialize($tags, 'json');

        return (new JsonResponse())->setJson($json);
    }

    /**
     * Permanently remove one tag from **every** entry by passing the Tag ID.
     *
     * @Operation(
     *     tags={"Tags"},
     *     summary="Permanently remove one tag from every entry by passing the Tag ID.",
     *     @OA\Parameter(
     *         name="tag",
     *         in="path",
     *         description="The tag",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             pattern="\w+",
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returned when successful"
     *     )
     * )
     *
     * @Route("/api/tags/{tag}.{_format}", methods={"DELETE"}, name="api_delete_tag", defaults={"_format": "json"})
     *
     * @return JsonResponse
     */
    public function deleteTagAction(Tag $tag, TagRepository $tagRepository, EntryRepository $entryRepository)
    {
        $this->validateAuthentication();

        $tagFromDb = $tagRepository->findByLabelsAndUser([$tag->getLabel()], $this->getUser()->getId());

        if (empty($tagFromDb)) {
            throw $this->createNotFoundException('Tag not found');
        }

        $entryRepository->removeTag($this->getUser()->getId(), $tag);

        $this->cleanOrphanTag($tag);

        $json = $this->serializer->serialize($tag, 'json');

        return (new JsonResponse())->setJson($json);
    }

    /**
     * Remove orphan tag in case no entries are associated to it.
     *
     * @param Tag|array $tags
     */
    private function cleanOrphanTag($tags)
    {
        if (!\is_array($tags)) {
            $tags = [$tags];
        }

        foreach ($tags as $tag) {
            if (0 === \count($tag->getEntries())) {
                $this->entityManager->remove($tag);
            }
        }

        $this->entityManager->flush();
    }
}

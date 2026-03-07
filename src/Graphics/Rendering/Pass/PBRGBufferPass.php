<?php

namespace VISU\Graphics\Rendering\Pass;

use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\TextureOptions;

/**
 * Extended GBuffer pass that adds metallic/roughness and emissive outputs
 * for PBR rendering. Backwards-compatible: the standard GBufferPassData
 * fields are populated, plus additional PBR-specific textures.
 */
class PBRGBufferPass extends GBufferPass
{
    public function setup(RenderPipeline $pipeline, PipelineContainer $data): void
    {
        // let the parent create the standard GBuffer (position, view position, normal, albedo, depth)
        parent::setup($pipeline, $data);

        $gbufferData = $data->get(GBufferPassData::class);
        $pbrData = $data->create(PBRGBufferData::class);

        // metallic (R) + roughness (G) packed into a single RG texture
        $mrOptions = new TextureOptions;
        $mrOptions->internalFormat = GL_RG16F;
        $mrOptions->dataFormat = GL_RG;
        $mrOptions->dataType = GL_FLOAT;
        $mrOptions->generateMipmaps = false;
        $pbrData->metallicRoughnessTexture = $pipeline->createColorAttachment(
            $gbufferData->renderTarget, 'metallic_roughness', $mrOptions
        );

        // emissive (RGB)
        $emissiveOptions = new TextureOptions;
        $emissiveOptions->internalFormat = GL_RGB16F;
        $emissiveOptions->dataFormat = GL_RGB;
        $emissiveOptions->dataType = GL_FLOAT;
        $emissiveOptions->generateMipmaps = false;
        $pbrData->emissiveTexture = $pipeline->createColorAttachment(
            $gbufferData->renderTarget, 'emissive', $emissiveOptions
        );
    }

    public function execute(PipelineContainer $data, PipelineResources $resources): void
    {
        // parent clears color + depth
        parent::execute($data, $resources);
    }
}

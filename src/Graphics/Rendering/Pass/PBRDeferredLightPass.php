<?php

namespace VISU\Graphics\Rendering\Pass;

use GL\Math\{GLM, Quat, Vec3};
use VISU\Component\DirectionalLightComponent;
use VISU\Component\PointLightComponent;
use VISU\Component\SpotLightComponent;
use VISU\ECS\EntitiesInterface;
use VISU\Geo\Transform;
use VISU\Graphics\GLState;
use VISU\Graphics\QuadVertexArray;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\ShaderProgram;

class PBRDeferredLightPass extends RenderPass
{
    const MAX_POINT_LIGHTS = 32;
    const MAX_SPOT_LIGHTS = 16;

    public function __construct(
        private ShaderProgram $lightingShader,
        private DirectionalLightComponent $sun,
        private EntitiesInterface $entities,
    ) {
    }

    public function setup(RenderPipeline $pipeline, PipelineContainer $data): void
    {
        $gbufferData = $data->get(GBufferPassData::class);
        $pbrGbufferData = $data->get(PBRGBufferData::class);
        $lightpassData = $data->create(DeferredLightPassData::class);

        $pipeline->reads($this, $gbufferData->albedoTexture);
        $pipeline->reads($this, $gbufferData->normalTexture);
        $pipeline->reads($this, $gbufferData->worldSpacePositionTexture);
        $pipeline->reads($this, $pbrGbufferData->metallicRoughnessTexture);
        $pipeline->reads($this, $pbrGbufferData->emissiveTexture);

        $lightpassData->renderTarget = $pipeline->createRenderTarget(
            'lightpass',
            $gbufferData->renderTarget->width,
            $gbufferData->renderTarget->height
        );
        $lightpassData->output = $pipeline->createColorAttachment(
            $lightpassData->renderTarget, 'lightpass_output'
        );
    }

    public function execute(PipelineContainer $data, PipelineResources $resources): void
    {
        $gbufferData = $data->get(GBufferPassData::class);
        $pbrGbufferData = $data->get(PBRGBufferData::class);
        $cameraData = $data->get(CameraData::class);
        $lightpassData = $data->get(DeferredLightPassData::class);
        $ssaoData = $data->get(SSAOData::class);

        $resources->activateRenderTarget($lightpassData->renderTarget);

        /** @var QuadVertexArray */
        $quadVA = $resources->cacheStaticResource('quadva', function(GLState $gl) {
            return new QuadVertexArray($gl);
        });

        $this->lightingShader->use();
        $this->lightingShader->setUniformVec3('camera_position', $cameraData->renderCamera->transform->position);
        $this->lightingShader->setUniform2f('camera_resolution', $cameraData->resolutionX, $cameraData->resolutionY);

        // directional light (sun)
        $this->lightingShader->setUniformVec3('sun_direction', $this->sun->direction);
        $this->lightingShader->setUniformVec3('sun_color', $this->sun->color);
        $this->lightingShader->setUniform1f('sun_intensity', $this->sun->intensity);

        // view matrix for cascade depth calculation
        $this->lightingShader->setUniformMatrix4f('u_view_matrix', false, $cameraData->view);

        // point lights
        $lightIndex = 0;
        foreach ($this->entities->view(PointLightComponent::class) as $entity => $light) {
            if ($lightIndex >= self::MAX_POINT_LIGHTS) break;

            $transform = $this->entities->get($entity, Transform::class);
            $worldPos = $transform->getWorldPosition($this->entities);
            $prefix = "point_lights[{$lightIndex}]";

            $this->lightingShader->setUniformVec3("{$prefix}.position", $worldPos);
            $this->lightingShader->setUniformVec3("{$prefix}.color", $light->color);
            $this->lightingShader->setUniform1f("{$prefix}.intensity", $light->intensity);
            $this->lightingShader->setUniform1f("{$prefix}.range", $light->range);
            $this->lightingShader->setUniform1f("{$prefix}.constant", $light->constantAttenuation);
            $this->lightingShader->setUniform1f("{$prefix}.linear", $light->linearAttenuation);
            $this->lightingShader->setUniform1f("{$prefix}.quadratic", $light->quadraticAttenuation);
            $lightIndex++;
        }
        $this->lightingShader->setUniform1i('num_point_lights', $lightIndex);

        // spot lights
        $spotIndex = 0;
        foreach ($this->entities->view(SpotLightComponent::class) as $entity => $spot) {
            if ($spotIndex >= self::MAX_SPOT_LIGHTS) break;

            $transform = $this->entities->get($entity, Transform::class);
            $worldPos = $transform->getWorldPosition($this->entities);

            // transform local direction by entity orientation
            $worldDir = Quat::multiplyVec3(
                $transform->getWorldOrientation($this->entities),
                $spot->direction,
            );

            $prefix = "spot_lights[{$spotIndex}]";

            $this->lightingShader->setUniformVec3("{$prefix}.position", $worldPos);
            $this->lightingShader->setUniformVec3("{$prefix}.direction", $worldDir);
            $this->lightingShader->setUniformVec3("{$prefix}.color", $spot->color);
            $this->lightingShader->setUniform1f("{$prefix}.intensity", $spot->intensity);
            $this->lightingShader->setUniform1f("{$prefix}.range", $spot->range);
            $this->lightingShader->setUniform1f("{$prefix}.constant", $spot->constantAttenuation);
            $this->lightingShader->setUniform1f("{$prefix}.linear", $spot->linearAttenuation);
            $this->lightingShader->setUniform1f("{$prefix}.quadratic", $spot->quadraticAttenuation);
            $this->lightingShader->setUniform1f("{$prefix}.innerCutoff", cos(GLM::radians($spot->innerAngle)));
            $this->lightingShader->setUniform1f("{$prefix}.outerCutoff", cos(GLM::radians($spot->outerAngle)));
            $spotIndex++;
        }
        $this->lightingShader->setUniform1i('num_spot_lights', $spotIndex);

        // bind GBuffer textures
        $texUnit = 0;
        $textureBindings = [
            [$gbufferData->worldSpacePositionTexture, 'gbuffer_position'],
            [$gbufferData->normalTexture, 'gbuffer_normal'],
            [$gbufferData->depthTexture, 'gbuffer_depth'],
            [$gbufferData->albedoTexture, 'gbuffer_albedo'],
            [$ssaoData->blurTexture, 'gbuffer_ao'],
            [$pbrGbufferData->metallicRoughnessTexture, 'gbuffer_metallic_roughness'],
            [$pbrGbufferData->emissiveTexture, 'gbuffer_emissive'],
        ];

        foreach ($textureBindings as [$texture, $name]) {
            $glTexture = $resources->getTexture($texture);
            $glTexture->bind(GL_TEXTURE0 + $texUnit);
            $this->lightingShader->setUniform1i($name, $texUnit);
            $texUnit++;
        }

        // bind shadow maps and set cascade uniforms
        $hasShadows = $data->has(ShadowMapData::class);
        if ($hasShadows) {
            $shadowData = $data->get(ShadowMapData::class);
        }
        if ($hasShadows && $shadowData->cascadeCount > 0) {
            $this->lightingShader->setUniform1i('num_shadow_cascades', $shadowData->cascadeCount);

            for ($i = 0; $i < $shadowData->cascadeCount; $i++) {
                $shadowTex = $resources->getTexture($shadowData->depthTextures[$i]);
                $shadowTex->bind(GL_TEXTURE0 + $texUnit);
                $this->lightingShader->setUniform1i("shadow_map_{$i}", $texUnit);
                $texUnit++;

                $this->lightingShader->setUniformMatrix4f("light_space_matrices[{$i}]", false, $shadowData->lightSpaceMatrices[$i]);
                $this->lightingShader->setUniform1f("cascade_splits[{$i}]", $shadowData->cascadeSplits[$i]);
            }
        } else {
            $this->lightingShader->setUniform1i('num_shadow_cascades', 0);
        }

        glDisable(GL_DEPTH_TEST);
        glEnable(GL_CULL_FACE);

        $quadVA->bind();
        $quadVA->draw();
    }
}

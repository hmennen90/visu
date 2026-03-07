#version 330 core

#include "visu/fullscreen_quad.glsl"

out vec2 v_texture_cords;

void main()
{
    gl_Position = vec4(quad_vertices[gl_VertexID], 0.0, 1.0);
    v_texture_cords = quad_uvs[gl_VertexID];
}

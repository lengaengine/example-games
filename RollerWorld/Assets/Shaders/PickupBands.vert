#version 330

in vec3 vertexPosition;
in vec2 vertexTexCoord;
in vec3 vertexNormal;
in vec4 vertexColor;

uniform mat4 mvp;
uniform mat4 matModel;
uniform mat4 matNormal;

out vec2 fragTexCoord;
out vec4 fragColor;
out vec3 fragLocalPosition;
out vec3 fragWorldPosition;
out vec3 fragWorldNormal;

void main()
{
    fragTexCoord = vertexTexCoord;
    fragColor = vertexColor;
    fragLocalPosition = vertexPosition;
    vec4 worldPosition = matModel * vec4(vertexPosition, 1.0);
    fragWorldPosition = worldPosition.xyz;
    fragWorldNormal = normalize(mat3(matNormal) * vertexNormal);

    gl_Position = mvp * vec4(vertexPosition, 1.0);
}
